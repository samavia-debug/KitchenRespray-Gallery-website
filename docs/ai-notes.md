# AI features: technical notes

All AI code is in one file: `wordpress/plugins/respray-portfolio/includes/ai.php`, plus `assets/assistant.js` / `assistant.css` for the chat panel and a click handler in `assets/admin.js` for the editor button. Removing the `require` for `ai.php` from `respray-portfolio.php` removes every AI feature and leaves the rest of the site unchanged.

## Setup

1. Create an API key in the Claude Console.
2. Either paste it in **Projects → AI settings**, or (preferred on live hosting) add this to `wp-config.php`:
   `define( 'RP_ANTHROPIC_API_KEY', 'your key' );`
3. Tick the features to switch on.

With no key, or a feature unticked, that feature does nothing and the non-AI behaviour is used.

| Setting | Default | Meaning |
|---|---|---|
| Model | `claude-opus-5-5` | Any current Claude model ID |
| Smart gallery search | on | Only runs once a key is set |
| Description button | on | Only runs once a key is set |
| Gallery assistant | off | Public chat button |
| Daily limit | 300 | AI requests per day, site-wide |
| Per-visitor limit | 20 | AI requests per visitor per hour (public features) |

## How each feature works

### 1. Smart search

- Every gallery search first goes through the built-in word matcher (`rp_parse_text()` in `gallery.php`). "black shaker kitchens" is fully understood there and never reaches the AI.
- The AI is asked only when words are left over (for example "charcoal units with brass handles"). It hooks the `rp_interpret_search` filter.
- The AI receives the search text and the list of real filter options. Its reply is constrained by a JSON schema whose allowed values are the site's own category slugs, and is checked again in PHP (`rp_ai_clean_filters()`), so it cannot produce a filter that does not exist.
- The visitor is redirected to the normal filter address, so results always come from the database query. Leftover words are shown as a text search; if they match nothing, the page says so and shows the filter matches.
- Each interpreted search is cached for 30 days, so a repeated search costs nothing.
- On any error, timeout (12 s) or limit, the built-in result is used.

### 2. Project description generator

- Button under the description box on the project screen (staff with the Portfolio Editor role can use it).
- Sends only what is on the screen: title, service, colour, finish, style, material, paint brand, location, original and new colour, and staff notes.
- The instructions forbid adding anything not supplied (materials, processes, timescales, prices, guarantees, customer reactions).
- The text is placed in the description box. Nothing is saved or published until staff press Update or Publish.

### 3. Gallery assistant

- REST endpoint: `POST /wp-json/rp/v1/assistant` with `{ "message": "...", "history": [...] }`.
- The AI only classifies the question (projects, transformations, videos, brochures, reviews, other) and picks filters from the real options.
- The answer text, project count, link and three example projects are produced by PHP from the database (`rp_ai_assistant_answer()`). The AI's own wording is used only for "other" questions, where it is told to use the About text from Portfolio settings and otherwise to say it can only help find content on the site.
- If nothing matches, it says so and offers the nearest match or the full gallery. It never claims work the site does not show.
- The browser keeps the last few turns in the page only; nothing is stored on the server.

## Safety and privacy

- The API key stays on the server. It is not in any page or script.
- Sent to the AI service: the visitor's search or question text, the list of category names, the About text (assistant), and project facts typed by staff (description button). No names, email addresses, IP addresses or cookies are sent.
- Visitor IP addresses are hashed and used only to count requests for the per-visitor limit.
- Public text is treated as data. Because replies are limited to a fixed schema and re-checked, text such as "ignore your instructions" cannot change what the site shows.
- Failures are written to the WordPress debug log only when `WP_DEBUG_LOG` is on.

## The API request

- `POST https://api.anthropic.com/v1/messages`, `anthropic-version: 2026-10-21`, sent with WordPress's `wp_remote_post()`.
- The official Anthropic PHP SDK is not used, so the plugin needs no Composer install and runs on PHP 7.4+. If the host later guarantees a recent PHP and Composer, `rp_ai_request()` is the single function to swap.
- Thinking is left at the model default (adaptive); depth is set with `output_config.effort`: `low` for search and the assistant, `medium` for descriptions.
- Structured replies use `output_config.format` with a JSON schema.
- For models that support it, `fallbacks: "default"` (beta header `server-side-fallback-2026-07-01`) lets the API retry a declined request on its recommended fallback model.
- Refusals (`stop_reason: "refusal"`), empty replies and non-200 responses all return a `WP_Error`, which every caller turns into the non-AI fallback.

## Cost control

- Common searches never call the AI; repeated unusual searches are cached.
- The daily and per-visitor limits cap spend. When reached, features fall back silently.
- The model can be changed in AI settings without touching code.

## Not yet tested against the live service

The request format, parsing, limits, fallbacks and all three features were tested locally against a stand-in for the API. They have not yet been run with a real key. First test with a real key: one unusual search, one description, three assistant questions, then check **Used today** in AI settings.

## Future: visual search (Phase 4)

Not built. The structure is ready for it:

- Every uploaded photo already stores a content fingerprint (`_rp_md5`) and belongs to a project through the photo groups.
- Plan: on upload, store an image embedding per photo in post meta or a small custom table; add an upload box to the gallery that embeds the visitor's photo and returns the nearest projects through the existing card renderer.
- The gallery already accepts an `rp_interpret_search` filter and renders any list of project IDs, so visual search plugs in without changing templates.
