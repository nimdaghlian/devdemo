
- https://demo.ideastore.dev/rss-feeds
	- [x] replace smallweb tag examples with "cats" everywhere
	- [x] nim test all of em
- https://demo.ideastore.dev/image-indexing
	* [x] results in sidebar should include `image`
	* [x] they do but now we need to check if they'll not get duplicated
- https://demo.ideastore.dev/badge-indexing
- https://demo.ideastore.dev/tags-and-octothorpes
	- [x] Nim to rewrite
- https://demo.ideastore.dev/pure-http
	- [x] sidebar is missing "testing" thorpe
- https://demo.ideastore.dev/multi-server
- https://demo.ideastore.dev/alt-server
	- [x] need to use correct template and confirm we can get data back from the alt-server and differentiate it
- https://demo.ideastore.dev/multi-platform
	- [x] update wordpress link
- https://demo.ideastore.dev/relationship-terms
	- [x] server results should include relationship terms
	- [x] well fuck now they're on every link.
- https://demo.ideastore.dev/backlinked-page
	- [x] no-backlinks shouldn't backlink to this page! delete record on the server
- https://demo.ideastore.dev/post-date
- [x] the meta tag mentioned on this page should actually go in the head and get indexed
- https://demo.ideastore.dev/web-components
	- [x] update to reflect the compact default for the main web component
	- [x] replace references to smallweb
	- [ ] move documentation to docs
	- [x] fix the rawhtml display
	- [ ] also add the webring component?
	* [x] man, octo-thorpe should take innerhtml < see below
- https://demo.ideastore.dev/match-all
	- [ ] move to docs
- https://demo.ideastore.dev/demo-webring
	- [x] why is it showing "not yet indexed"?

## Defenses section
- [ ] front page can become a toc
- [ ] whole thing moves to docs
- [ ] we should think about how to trigger the actual responses, since all of these just fire "cannot index pages from a different origin." spoof headers?

* [x] Pages on demo.ideastore should have fuckin title values 
* [x] footer shouldn't rise off the bottom of the screen, maybe remove the color
* [ ] also change the links
* [x] In action on this page, etc should only show up if the separator is there

Sidebar shit

* [x] sidebar return should have a "api" source link that goes to the debug endpoint for this page
* [ ] should add a focused "docs related to this page" component that displays links to the current page scoped to anything coming from docs.octothorp.es 
* [x] sidebar text should be smaller and monospaced with less line spacing
* [x] does sidebar have a problem with webrings?


## bigger problems or probs for later

* [ ] should stop trying to parse stylesheets. causing timeouts
* [x] seems like there are timeouts on extant backlink
* [ ] `~/term/rss` seems to be broken
* [ ] can badge.js deliver the image with `image-rendering: pixelated;` as a class, or is that not possible from the endpoint as-is?


## update ocoto-thorpe

If you want the new web component to be a drop-in for that, two pieces:

1. **Accept inner text as the term** — match `node.getAttribute("href") || node.innerText.trim()` from `tag.js:147`. In Svelte custom elements this means reading `this.textContent` on connect (and falling back to the `o` attribute for back-compat).
2. **Inject the preload link on mount if missing** — port the `tag.js:91-116` logic. Read `server` from the existing prop, build `${server}?uri=${encodeURI(window.location.href)}`, and either create a new `<link rel="preload" as="fetch">` in `document.head` or fill an empty existing one.

