# Incident — 2026-07-13 — Full VPS outage (7 sites, ~40 min)

**Severity:** SEV-1. Total outage of a shared production VPS, including
`americanguntrader.com`, which had live users at the time.

**Cause:** An infinite recursion in the Digest theme, written by Claude (agent),
combined with a missing PHP-FPM guardrail on the host.

**Author of the fault:** Claude, working on the Digest theme. This is not a
"WordPress did something odd" incident. A function called a renderer that
re-entered the same function, and it was deployed to a live shared host without
ever having been executed anywhere first.

---

## 1. Impact

Every site on `93.95.229.147` went down, not just the two being worked on.

| Site | Impact | Was it being worked on? |
|---|---|---|
| **americanguntrader.com** | **Down. Live users affected.** | **No — innocent bystander** |
| dabdash.com | Down | No |
| shadowsoftware.com | Down | No |
| api.shadowsoftware.com | Down | No |
| auralabs.asia | Down | No |
| cannabisdigest.net | Down | Yes |
| marksmansdigest.com | Down | Yes |

The operator was also **locked out of SSH** for the duration, and did not have
the provider console root password, so the only available remedy was a cold power
cycle from the hosting panel.

Recovery was a full cold boot. Nothing was lost: no data, no database, no files.

---

## 2. Root cause — the recursion

`digest_render_toc()` built the table of contents by rendering the post and
scraping the headings out of the result:

```php
// THE BUG
function digest_extract_headings( string $content ): array {
    $rendered = do_blocks( $content );   // <-- re-renders EVERY block in the post
    preg_match_all( '#<h([23])\b...#', $rendered, $matches );
    ...
}
```

`do_blocks()` renders every block in the post content. The post content contains
the table-of-contents block. Rendering it calls `digest_render_toc()`, which
calls `digest_extract_headings()`, which calls `do_blocks()` again.

There is no base case. It recurses until the process dies or the request is
killed — and, as section 3 explains, nothing was killing the request.

**Why it was not caught:** the theme was deployed straight to a live shared host
and exercised there. It had never been run locally, not once. The front page
happened to work (no TOC block on it), which created a false sense that the
theme was sound. The very first request to an article page — the first page that
contained the block — began spinning immediately.

### The fix

The rendering was never necessary. A `core/heading` block saves its `<h2>`/`<h3>`
markup verbatim into `post_content`; the headings are already sitting there as
plain HTML. So the fix is to *not render anything*:

```php
function digest_extract_headings( string $content ): array {
    // Read the RAW content. Do NOT call do_blocks() here: rendering the content
    // renders every block in it, including the table-of-contents block that asked
    // for the headings, which renders the content again, forever.
    preg_match_all( '#<h([23])\b([^>]*)>(.*?)</h\1>#is', $content, $matches, PREG_SET_ORDER );
    ...
}
```

The theme now contains no call to `do_blocks()` at all.

---

## 3. Contributing cause — no request timeout on PHP-FPM

A single recursive function should not be able to take down seven websites. It
could, because of a pre-existing gap in the host configuration.

**`request_terminate_timeout` was not set on any PHP-FPM pool.**

```
$ grep -rn request_terminate_timeout /etc/php/8.5/fpm/
(no matches)
```

> **FIXED 2026-07-13.** Every pool now sets `request_terminate_timeout = 120s`
> with `php_value[max_execution_time] = 110` beneath it. The change is versioned
> in `shadow-vps-infrastructure` at `config/php-fpm/*.conf` and deployed through
> that repo's `scripts/deploy.sh`, which does a full `systemctl restart
> php8.5-fpm` — FPM does **not** re-read this directive on `reload` (SIGUSR2),
> which is a trap worth knowing.

`php.ini` sets `max_execution_time = 30`, which reads like a guardrail and is
not one. Under FPM, `max_execution_time` measures *CPU time inside the script*
and is not enforced during blocking calls or in many re-entrant paths. The
setting that actually kills a runaway FPM request is `request_terminate_timeout`,
and it was absent. **A hung request therefore ran forever.**

### The arithmetic that turned one bad page into a dead box

> **Correction.** An earlier version of this document put the worst case at
> 9,728 MB, using the 128M `memory_limit` from `php.ini`. That was wrong:
> `memory_limit` is overridden **per pool**, and the real numbers are far worse.
> The corrected figures are below. I got this wrong by reading the global config
> instead of the pool configs, which is the same class of mistake as the bug
> itself — assuming rather than checking.

| Pool | children | memory_limit | worst case |
|---|---|---|---|
| dabdash | 25 | 512M | 12,800 MB |
| shadowsoftware | 16 | 256M | 4,096 MB |
| agt | 15 | 256M | 3,840 MB |
| auralabs | 8 | 256M | 2,048 MB |
| cannabisdigest | 6 | 256M | 1,536 MB |
| marksmansdigest | 6 | 256M | 1,536 MB |
| **Total** | **76** | | **25,856 MB** |

```
76 workers, worst case    25,856 MB
Box has                    7,940 MB
                          ----------
Over-committed by         17,916 MB   (325% of physical RAM)
```

Over-committing is normally harmless — workers finish, free their memory, and the
ceiling is never approached. It stops being harmless the moment workers *stop
finishing*. Each hung recursive request pinned a worker holding up to its pool's
`memory_limit` (256–512 MB) and never released it. They accumulated until the kernel had no memory left to fork
`sshd` — which is exactly what the operator experienced: TCP connected on port 22,
but the SSH banner never arrived.

**This is why AGT went down.** AGT is a different site, in a different pool, and
my bug was nowhere near its code. But memory is shared. Hung workers in the
`marksmansdigest` pool starved every other pool on the box.

---

## 4. Aggravating cause — how the bug was investigated

The recursion was the fault. The outage was made materially worse by how it was
handled:

1. The article page returned a gateway timeout.
2. It was requested **again** — with `curl`, then with `wp eval-file`, then with
   Playwright, then with `curl` again — each attempt spawning another worker that
   would never exit.
3. Every retry made the box less able to recover, and less able to accept the SSH
   connection that would have let it be fixed.

A page that times out is not a page to retry. It is a page to stop touching. The
correct move after the *first* timeout was to stop issuing requests and read the
code — the recursion is visible on inspection, and finding it required no server
at all.

---

## 5. Timeline

| Time | Event |
|---|---|
| — | Theme built locally. **Never executed locally — no local WordPress existed.** |
| — | Theme deployed straight to both live sites via `rsync`, activated, content seeded. |
| — | Front page verified in Playwright. **Looked correct.** It has no TOC block, so the recursion never fired. |
| T+0 | First request to an article page. Recursion begins. Worker hangs. |
| T+1m | Page appears to "hang". Retried with curl, wp-cli, Playwright — each retry hangs another worker. |
| T+3m | SSH begins timing out during banner exchange (box cannot fork). |
| T+4m | All 7 sites return Cloudflare 530 — the tunnel is starved out. AGT is down. |
| T+8m | Root cause identified by reading the code. Fix written locally. Cannot be deployed — no SSH. |
| T+10m | Escalated to the operator. Recommended: reboot, then stop php-fpm before anything can crawl the sites. |
| T+12m | Operator reboots. Soft reboot does not recover the box (it was likely too starved to run shutdown). |
| T+20m | Operator performs a **cold power cycle**. |
| T+24m | Box boots. SSH answers. |
| T+25m | Both digest sites switched to the stock `twentytwentyfive` theme — recursion disarmed. |
| T+26m | **All 7 sites verified 200 OK. AGT serving normally.** |

---

## 6. What actually saved it

The cold boot. Nothing else was available, because:

- SSH was unreachable (box could not fork).
- The operator did not have the provider console root password.
- The Cloudflare tunnel was down, so the sites could not even be taken offline at
  the edge to relieve pressure.

That is an uncomfortably thin set of options for a box running a live marketplace.

---

## 7. Actions

### Done

- [x] Recursion removed. `do_blocks()` no longer appears anywhere in the theme.
- [x] Both digest sites reverted to the stock theme; the broken theme is inert.
- [x] All 7 sites verified back up.

### Required before the Digest theme goes near that server again

- [ ] **Stand up a local WordPress.** The theme must run locally, on every
      template — front page, **article**, archive, search, 404 — before it is
      deployed anywhere. This incident exists because that step was skipped.
- [ ] **Add a recursion guard** to the theme, so that even a future mistake of
      this shape degrades to a missing block rather than a hung worker.
- [ ] **Regression test** that renders a post containing a TOC block and asserts
      it terminates.
- [ ] Deploy to **one** site, verify, and only then the second.

### Required on the host (independent of this theme)

- [x] **Set `request_terminate_timeout` on every PHP-FPM pool.** Done: 120s, with
      `max_execution_time = 110` beneath it. Versioned in
      `shadow-vps-infrastructure` at `config/php-fpm/*.conf`. This is the
      guardrail whose absence let one bad page kill seven sites; it should have
      been there before this incident, and it is there now regardless of Digest.
      **Note the trap:** FPM does not re-read this on `reload` — a full
      `systemctl restart php8.5-fpm` is required, which is what `deploy.sh` does.
- [ ] **Reconcile `max_children` against physical memory** — 76 workers at
      256–512 MB each is 25.8 GB worst case on a 7.9 GB box (325 %). Deliberately
      NOT changed during the incident response: throttling AGT or DabDash to guard
      against a bug in a news theme would trade one outage for another. This wants
      a considered decision, not a reflex.
- [ ] **`config/php-fpm/shadowsoftware.conf` is missing from the infra repo** but
      the pool exists on the box and `deploy.sh` does not manage it. Unmanaged
      drift that predates this incident.
- [ ] **Get the operator out-of-band access that does not depend on the box being
      healthy** — a working provider console login, or a root password reset. The
      operator was locked out of their own server during a live outage. That must
      not be true next time.

---

## 7a. A second lesson, learned while fixing the first

While validating the fix, the recursion was deliberately reintroduced to prove the
new re-entrancy guard worked. It did: the page rendered in 116 ms instead of
hanging forever.

**And then the smoke test reported `ALL TEMPLATES PASS — safe to consider
deploying`, with the bug sitting in the file.**

That is the original mistake in miniature. `local-smoke.sh` checks status codes
and response times. The guard converts the hang into a fast-but-wrong page — a
good trade, but it means a liveness check can no longer see the bug at all. A test
that only asks *"did the page load?"* will bless broken code, and blessing broken
code is how this incident happened.

So a second test exists: `scripts/local-assert.php`, which asserts what the page
must **contain**.

The decisive assertion is a heading count. The canary post has exactly **three**
headings in its raw content. The FAQ block renders additional `<h3>` elements, but
only *after* `do_blocks()` expands it — those headings are not in the raw content.
So:

| TOC items | Meaning |
|---|---|
| **3** | The TOC read the raw content. Correct. |
| **6** | The TOC rendered the content to read it. **The outage bug is back.** |

Proven against both versions of the code:

| | Bug present | Outcome |
|---|---|---|
| `local-smoke.sh` (liveness) | ✅ "safe to deploy" | **would have shipped it** |
| `local-assert.php` (correctness) | ❌ "5 FAILED — DO NOT DEPLOY" | **catches it** |

**Both must pass before any deploy.** Liveness alone is not a gate; it is a
formality that makes you feel gated.

---

## 7b. A third lesson, learned during the slug rename

The theme had to be renamed (`digest` is taken on WordPress.org). The rename
rewrote every `digest_` identifier to `shadow_digest_` — including, unintentionally,
the **setting IDs** declared in the Customizer:

```php
'digest_newsletter_blurb' => array( ... )   ->   'shadow_digest_newsletter_blurb' => array( ... )
```

The values already in the database were still stored under the *old* keys. So
every lookup missed, and `shadow_digest_get()` fell back to the theme's
**defaults** — which are the Marksman's ones.

**Cannabis Digest went live serving "The Weekly Dispatch" and "the week in
marksmanship" to its readers.**

Consider what did *not* catch this:

| Check | Result |
|---|---|
| PHP lint | ✅ pass |
| JSON / JS lint | ✅ pass |
| `do_blocks()` grep | ✅ pass |
| Every template renders, fast, no fatals | ✅ pass |
| 32 correctness assertions | ✅ pass |
| Live front page + article, 200 OK | ✅ pass |
| Theme mods present in the database | ✅ pass (they *were* — under the old keys) |
| **A human reading the rendered page** | ❌ **caught it** |

Nothing errored. Nothing was slow. Every block rendered. The site was simply,
quietly, wearing another publication's clothes — and would have kept doing so
indefinitely.

The fix was `scripts/migrate-modkeys.php`. The *durable* fix is the assertion now
in `local-assert.php`, which checks that a value the sandbox **sets** actually
appears in the **HTML** — so a broken settings path fails loudly instead of
silently substituting defaults.

**The general lesson, stated once, having now been learned three times in one
day:** a green test suite is evidence, not proof. Look at the page.

---

## 8. Lessons

**For the agent (me):**

1. **Never deploy code to a live shared host that has not been executed
   anywhere.** "It lints" is not "it runs." The recursion would have surfaced on
   the first local page load, in seconds, at zero cost.
2. **A hang is not a flake. Stop and read the code.** Retrying a timing-out page
   four different ways turned a broken page into a dead server. The recursion was
   plainly visible in a function I had written myself an hour earlier.
3. **"The front page looks right" is not "the theme works."** The one template
   that was verified was the one template that could not trigger the bug.
4. **A shared host means the blast radius is every tenant.** Work on a low-stakes
   site is not low-stakes work when a marketplace shares the box.

**Structurally:**

5. A missing `request_terminate_timeout` converts *any* bug of this class — in
   any theme, any plugin, any site on the box — into a total outage. The bug was
   mine; the fragility was pre-existing, and it is worth fixing on its own merits.

---

*Written by Claude, who caused this. The account is deliberately unflattering
because a sanitised one would be useless.*
