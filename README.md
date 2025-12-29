# rssync

A self-hosted RSS aggregator that lets you combine multiple feeds into curated lists with per-source filtering.

## What it does
- **Combine Feeds**: Group multiple RSS sources into a single list.
- **Filter**: Set whitelists or blacklists for authors and categories on a per-source basis.
- **Export**: Every list generates its own RSS feed URL.
- **Images**: Automatically pulls images from enclosures or scrapes them from the feed content.
- **Privacy**: Lists can be public or private to your account.

## Tech Stack
- **Framework**: Slim 4
- **Database**: Eloquent (MySQL)
- **Templates**: Twig
- **Parsing**: Laminas Feed
- **Migrations**: Phinx

## Installation

1. **Install dependencies**
   ```bash
   composer install
   ```

2. **Configuration**
   ```bash
   cp env.example .env
   # Edit .env with your database and mail credentials
   ```

3. **Database Setup**
   ```bash
   composer migrate
   # Optional: composer seed
   ```

4. **Run**
   ```bash
   php -S localhost:8080 -t public
   ```

## Feed Updates
The app doesn't refresh feeds automatically on page load to keep things fast. Set up a cron job to hit the refresh endpoint:

```cron
# Refresh all feeds every 30 minutes
*/30 * * * * curl -s http://your-site.com/api/refresh/all > /dev/null 2>&1
```

## License
MIT