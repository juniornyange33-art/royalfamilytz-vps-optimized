## Accessibility & Performance Quick Wins

This file documents small, low-risk changes to improve accessibility and perceived performance.

Recommended quick tasks:

- Ensure all images include descriptive `alt` text.
- Ensure form inputs have associated `<label>` elements and `aria-label` where needed.
- Add visible focus styles for keyboard users (see `accessibility.css`).
- Minify CSS/JS in production builds and enable gzip/brotli on the server.
- Add `rel="preload"` for critical fonts and hero images.

How to run Lighthouse locally:

```bash
# point this action to your site by setting the secret LIGHTHOUSE_TARGET
```
