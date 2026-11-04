# Temporary public test host

This project includes an optional Render deployment blueprint for a temporary review site. It runs the PHP application in demo mode, seeds its clearly labeled catalog on startup, sets canonical URLs from Render's assigned host, and never enables production checkout.

## Limits

- The service is configured for Render's Free web service. Free services sleep after 15 minutes idle, so the first page load after a quiet period can take about a minute.
- Free service disks are ephemeral. Demo accounts, baskets, test orders, and generated quotes can be lost when the service restarts, sleeps, or deploys. Do not enter real customer information.
- Render may suspend free services at its discretion or when usage limits are reached. This is for evaluation only, never live commerce.
- The preview is public to anyone who receives its URL. It is labeled and set to `noindex`, but it has no invitation gate.

## Deploy

The Render integration needs a source repository it can read. Keep that repository private. Connect the Render service to the repository and choose the `crm-technology-preview` blueprint in `render.yaml`; use the Free web plan and do not attach a paid database or persistent disk. Render will build the Docker image and provide the public `onrender.com` address. The startup command creates a fresh SQLite demo database and seeds the sample catalog when the service starts.

The source must be uploaded to a private Git provider repository before Render can build it. Do not commit `.env`, `storage/commerce.sqlite`, private backups, or credentials. The archive linked in the handoff excludes these files. If Render's free plan, repository access, or required build features are unavailable in the connected account, stop before selecting a paid plan and ask the owner.

## Later move to Afrihost

Use `docs/DEPLOYMENT.md` for the Afrihost staging and release procedure. Render is only a temporary review host; it does not replace the intended Linux shared-hosting deployment.
