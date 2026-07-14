# Shadow Software — WordPress repo conventions

How our public WordPress repos are named, described, decorated and released.
Written down because we have now got the naming wrong twice, in public, at the cost
of a review cycle each time.

---

## 1. The name

```
<product>-<kind?>-for-<platform>
```

`<platform>` is **the word our buyer actually searches**. That is the whole point of
the convention: these repos are storefronts, and the platform keyword is the
strongest term in the name.

| Repo | Why |
|---|---|
| `crypto-for-woocommerce` | An e-commerce plugin. The buyer searches **WooCommerce**. |
| `agt-for-woocommerce` | Same. |
| `broadside-theme-for-wordpress` | A **theme**. Themes are not WooCommerce products, and WordPress.org forbids "WooCommerce" in a theme name outright (trademark). |
| `broadside-blocks-for-wordpress` | A blocks plugin with **zero** WooCommerce code. Naming it `-for-woocommerce` would be a lie, and WP.org rejects a trademarked term in the slug of a plugin that is not about that product. |

**Do not put the platform in the name if the product is not for that platform.** The
keyword is worth nothing if it is not true, and WordPress.org will reject it.

### The trap: the GitHub repo name is NOT the WordPress.org slug

They are different identifiers with different lifetimes, and conflating them is how
you break a live plugin.

|  | GitHub repo name | WordPress.org slug |
|---|---|---|
| Changeable? | **Yes** — GitHub 301-redirects the old URL | **No. Permanent, forever.** |
| What it is | discovery, marketing | the SVN path, the **update-check key**, the text domain, the main PHP filename |
| Cost of changing | update `Plugin URI`, `homepage`, local remotes | **every existing install stops receiving updates** |

So `crypto-for-woocommerce` (repo) ships a plugin whose slug is still
`shadow-software-crypto-for-woocommerce`, because that plugin is live on
WordPress.org with real installs. **That divergence is correct and deliberate.**
Rename the repo freely; never rename a published slug.

Themes have the same rule *and two more*: a theme name may not contain **"WordPress"**,
**"Theme"**, or start with **"Twenty"**, and the slug is derived from the name. That is
why the theme is `Broadside` on WordPress.org and `broadside-theme-for-wordpress` on
GitHub.

Before claiming any new name, check **both** — and check the open web, not just
`wordpress.org`, because a name can be free on the Directory and still be an
established commercial product (this is exactly how "Pressroom" got through):

```bash
curl -o /dev/null -w '%{http_code}\n' https://wordpress.org/plugins/<slug>/
curl -o /dev/null -w '%{http_code}\n' https://wordpress.org/themes/<slug>/
# then actually search the web for "<Name> WordPress theme"
```

`scripts/package.sh` in the Broadside repo enforces the theme half of this
mechanically, against the bytes being shipped.

---

## 2. The GitHub repo settings

Issues and Actions. Nothing else.

```bash
gh api -X PATCH repos/shadow-software/<repo> \
  -F has_issues=true \
  -F has_projects=false \
  -F has_wiki=false \
  -F has_discussions=false \
  -F has_downloads=true
```

Wiki, Projects and Discussions are surfaces we do not staff, and an unstaffed
support surface is worse than no support surface: it looks abandoned.

*(`allow_forking=false` is not available on public repos — GitHub only honours it on
private ones. Do not bother setting it and do not report it as done.)*

**Description** — one sentence, what it does and for whom, ending "by Shadow Software
LLC". It is the text that appears in search results.

**Homepage** — the **WordPress.org page if the product is published there**, otherwise
`https://shadowsoftware.com/`. The .org page converts better than our own site,
because it carries the install button.

---

## 3. The artwork

Every repo opens with the same banner: a dark radial ground, the faint hex lattice,
the **hooded-hexagon Shadow mark**, a product motif, and a wordmark block —
headline / subtitle / strapline / accent rule / "by Shadow Software".

Only two things vary: the **accent colour** and the **motif**.

| Product | Accent | Motif |
|---|---|---|
| Crypto | `#8fd468` green | an ETH coin |
| AGT | `#d9a441` brass | a rifle-scope reticle |
| Broadside | `#b34747` oxblood | a folded broadsheet |
| Broadside Blocks | `#b34747` oxblood | stacked block cards |

It is generated, not hand-cut: `.github/assets/make-banners.py` emits all four as SVG
and rasterises them with `rsvg-convert`. **It is not made with an image model** — the
design is type and vector, and a diffusion model cannot set type. (Ours can draw a
beautiful engraving; it cannot spell "WooCommerce".)

---

## 4. Releases

Every repo publishes its own installable `.zip` on a tag, built and verified by CI —
never assembled by hand. Push a tag, the workflow builds the artefact, runs the gates
against **the bytes that will actually be uploaded**, and creates the GitHub Release.

```bash
# bump the version in the three places that must agree, then:
git tag v1.2.3 && git push origin v1.2.3
```

The tag must match the version inside the package; the release workflow asserts it and
refuses to publish if they disagree. A zip whose filename and contents disagree about
what version they are is how a bad release reaches a user.

Plugins that are on WordPress.org additionally push to SVN (`deploy.yml`), which is a
separate step from the GitHub Release and needs the `SVN_USERNAME` / `SVN_PASSWORD`
secrets.

---

## 5. The README

Banner, H1, bold one-paragraph pitch, then badges/links — WordPress.org, the release,
the licence. If the product ships with a companion (the theme and its blocks plugin),
**say so in the first screen**, with a link, because they are useless apart.

Keep the incident and gotcha material in `docs/`, not in the README. The README sells;
`docs/` explains.
