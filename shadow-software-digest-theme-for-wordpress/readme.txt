=== Digest ===
Contributors: shadowsoftware
Requires at least: 6.6
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 1.0.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Tags: news, blog, one-column, two-columns, three-columns, custom-colors, custom-logo, custom-menu, block-patterns, block-styles, editor-style, featured-images, full-site-editing, rtl-language-support, style-variations, template-editing, translation-ready, wide-blocks, accessibility-ready

A block theme for news publications and journals of record. A blackletter masthead, a folio rule, a broadsheet lead grid, and the editorial furniture a long read needs.

== Description ==

Digest dresses a WordPress site as a broadsheet newspaper.

The front page is a three-column lead grid: a rail of briefs, a lead story set in
two justified columns with a drop cap, and a photo with the day's secondary story
beside it. Above them sits a blackletter nameplate flanked by two "ears", a folio
rule carrying the volume, the date and the motto, and a section navigation. Below
them, an index of every section and its latest headlines, built from your
categories.

Articles get the furniture a reported feature actually needs, as blocks:

* **Short Answer** — a direct, quotable answer at the top, for the reader in a
  hurry and the search engine that wants to cite you.
* **Key Takeaways** — the three or four things a reader should leave with.
* **Table of Contents** — builds itself from your headings. Rename a heading and
  the contents follow; there is nothing to keep in sync by hand.
* **FAQ** — questions and answers that also emit FAQPage structured data.
* **Sources & References** — the receipts.
* **Disclosure Table** — a comparison table whose partner links are always marked
  `rel="sponsored nofollow"` and which always prints a disclosure line.
* **Byline**, **Author Bio** and **Editorial Standards** — the trust signals a
  journal of record is judged on.

= One theme, two publications =

Digest hard-codes no brand. The accent colour, the founding year, the city of
record, the strapline, the motto, the cover price, the volume, the newsletter's
name and the masthead "ears" are all Customizer settings. Two sites running
Digest can look like two entirely different journals — one a green cannabis trade
weekly, the other an oxblood shooting-sports monthly — from the same code.

= What Digest does not do =

Digest renders a newsletter signup form and hands the address to whatever
endpoint you name in the Customizer. It **stores no subscribers and sends no
mail**. That is deliberate: your subscriber list is yours, and a theme that held
it would lose it the day you switched themes. Point the form at your own
automation, mailing-list provider, or webhook.

Digest also emits NewsArticle, Organization, BreadcrumbList and FAQPage
structured data — but only when no SEO plugin is active. If Yoast, Rank Math,
SEOPress or All in One SEO is running, they own the schema graph and Digest gets
out of the way, because two competing graphs on one page is worse than none.

= Accessibility =

Digest is built to the accessibility-ready guidelines: a working skip link, a
visible focus style on every interactive element, correct heading order (the
nameplate steps down from `h1` to `p` on any page where an article headline is
the real `h1`), form labels on every input, `prefers-reduced-motion` honoured, and
colour contrast that passes AA on the default palette. The Customizer will let you
pick an accent that fails contrast — it is your publication — but the defaults do
not.

== Installation ==

1. In your WordPress admin, go to Appearance → Themes → Add New.
2. Search for "Digest", then click Install and Activate.
3. Go to Appearance → Customize → Digest and set your accent colour, founding
   year, city, strapline and motto.
4. Go to Appearance → Editor to adjust the templates, or start writing — the
   theme works out of the box.

To reproduce the front page, you need a handful of published posts: the most
recent becomes the lead story, the next four fill the briefs rail, and the sixth
becomes the secondary story beside the photo.

== Frequently Asked Questions ==

= Does Digest require a page builder? =

No. Digest is a block theme built on core WordPress. It requires no plugin at
all, and it deliberately does not depend on Elementor, Divi, or any other page
builder — your layouts stay in your theme's files, under version control, rather
than in your database.

= Where do subscribers go when someone signs up? =

Wherever you send them. Digest renders the form and posts it to the endpoint you
set in Customizer → Digest → Newsletter. If you set no endpoint, no form is
rendered — Digest will not show a form that silently throws addresses away.

= The table of contents is empty. =

It builds itself from the `h2` and `h3` headings in the post. A post with no
headings has no contents to list. Add a heading and it appears.

= Can I use my own logo instead of the blackletter nameplate? =

Yes. Set a custom logo under Customizer → Site Identity and it replaces the
type entirely. You can also switch the nameplate to a cleaner display serif under
Customizer → Digest → Masthead.

= Why does the drop cap look wrong in my language? =

The drop cap uses `::first-letter`, which behaves differently across scripts. Turn
it off under Customizer → Digest → Article furniture if it does not suit your
language.

== Copyright ==

Digest, Copyright 2026 Shadow Software LLC.
Digest is distributed under the terms of the GNU GPL v2 or later.

This theme bundles the following resources:

UnifrakturMaguntia (assets/fonts/unifraktur-maguntia-400.woff2)
Copyright (c) 2010, Peter Wiegel; (c) 2013, j. 'mach' wust.
Licensed under the SIL Open Font License, Version 1.1.
Source: https://fonts.google.com/specimen/UnifrakturMaguntia
License: https://scripts.sil.org/OFL

Libre Caslon Display (assets/fonts/libre-caslon-display-400.woff2)
Copyright (c) 2019, Pablo Impallari, Rodrigo Fuenzalida, Igino Marini.
Licensed under the SIL Open Font License, Version 1.1.
Source: https://fonts.google.com/specimen/Libre+Caslon+Display
License: https://scripts.sil.org/OFL

Source Serif 4 (assets/fonts/source-serif-4-normal.woff2, source-serif-4-italic.woff2)
Copyright (c) 2014-2023, Adobe (https://adobe.com/), with Reserved Font Name 'Source'.
Licensed under the SIL Open Font License, Version 1.1.
Source: https://fonts.google.com/specimen/Source+Serif+4
License: https://scripts.sil.org/OFL

The full text of the SIL Open Font License 1.1 ships with this theme at
assets/fonts/LICENSE-OFL.txt.

The screenshot (screenshot.png) is a rendering of the theme itself, created by
Shadow Software LLC and licensed GPLv2 or later. It contains no third-party
imagery.

== Changelog ==

= 1.0.1 =
* **Fixed a fatal infinite recursion in the table-of-contents block.** It rendered
  the post content in order to find the headings, which re-rendered every block in
  that content — including itself — with no base case. Any article containing the
  block would hang the request forever. It now reads the raw post content, where
  the heading markup already lives, and renders nothing.
* Added a re-entrancy guard around every block render callback, so that no future
  bug of this shape can hang a request: a block that re-enters itself now returns
  empty instead of recursing.
* Author avatars no longer block page rendering on a Gravatar request. A site whose
  outbound HTTP is firewalled would hang on every avatar; Digest now prefers a
  locally-uploaded image, and falls back to a printed monogram.
* Replaced the related-posts query with a block that cannot recommend the article
  the reader is already on.
* Fixed related-post cards running their kicker, headline and summary together on
  one line.
* The newsletter panel no longer holds open an empty second column when no signup
  endpoint is configured.

= 1.0.0 =
* Initial release.
