# Portfolio + Bingo CI4 App

This project now serves a single-page portfolio at `/` and keeps the existing bingo game at `/bingo`.

## What changed

- `/` now renders a single-page portfolio view with placeholder content.
- `/bingo` and the existing bingo room endpoints remain unchanged.
- Portfolio content is centralized in the controller so it can be customized quickly.

## File map

- `app/Controllers/Portfolio.php`: portfolio content and view data.
- `app/Views/portfolio.php`: single-page portfolio markup.
- `public/portfolio.css`: portfolio styles.
- `app/Config/Routes.php`: routes `/` to the portfolio page.

## Local development

1. Install dependencies if needed:

	```bash
	composer install
	```

2. Copy `env` to `.env` if you want environment-specific settings.

3. Set your local base URL in `app/Config/App.php` or in `.env`.

4. Start the local CI4 server:

	```bash
	php spark serve
	```

5. Open:

	- `http://localhost:8080/` for the portfolio
	- `http://localhost:8080/bingo` for the game

## Deployment

1. Deploy this project to a PHP-capable host.

2. Point the domain or virtual host to the `public/` directory, not the project root.

3. Update the base URL for production in `app/Config/App.php`:

	```php
	public string $baseURL = 'https://ramanbharati.com.np/';
	```

4. Ensure Apache rewrite support is enabled and `public/.htaccess` is active.

5. After deployment, verify:

	- `https://ramanbharati.com.np/` loads the portfolio
	- `https://ramanbharati.com.np/bingo` still loads the game

## Customization

Update placeholder content in `app/Controllers/Portfolio.php`:

- `site.name`: your name
- `site.role`: your role or title
- `site.tagline`: hero description
- `site.location`: your location
- `site.email`: your email address
- `site.resumeUrl`: your resume link
- `site.githubUrl`: your GitHub profile
- `site.linkedinUrl`: your LinkedIn profile
- `site.gameUrl`: your real game URL

You can also edit:

- `about`: your introduction
- `skills`: the skills list
- `projects`: project cards and links
- `experience`: timeline items
- `education`: education entries

## Notes

- The game button currently uses the required placeholder URL `https://example.com/my-game`.
- The portfolio is intentionally single-page and does not use multi-page navigation.
- Cloudflare Pages alone is not suitable for this CI4 PHP app; use standard PHP hosting for this deployment.
