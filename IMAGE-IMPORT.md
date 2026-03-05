# Image Import - Supported URLs

The CSV import template supports an `image_url` column that automatically downloads, watermarks, and attaches images to art pieces during import.

## How It Works

1. Add a URL to the `image_url` column in your CSV
2. For multiple images, separate URLs with a pipe `|` character
3. During import, the plugin downloads each image
4. Images are watermarked and optimized (AVIF/WebP variants)
5. The first image becomes the primary image; additional images are attached in order
6. If any download fails, the art piece is still created — only that image is skipped

## Supported URL Sources

### Direct Image URLs (Any Host)
Any URL that directly serves an image file will work:
- `https://example.com/photos/sunset.jpg`
- AWS S3 URLs (`s3.amazonaws.com/...`)
- Google Cloud Storage (`storage.googleapis.com/...`)
- Azure Blob Storage (`*.blob.core.windows.net/...`)
- WordPress media library URLs from other sites
- Any publicly accessible `.jpg`, `.jpeg`, `.png`, `.gif`, or `.webp` file

### Auto-Converted Sharing Links

The following sharing URLs are automatically converted to direct download links:

| Service | Example URL | Notes |
|---------|------------|-------|
| **Google Drive** | `https://drive.google.com/file/d/{ID}/view?usp=drive_link` | File must be shared as "Anyone with the link" |
| **Google Drive** | `https://drive.google.com/open?id={ID}` | Same sharing requirement |
| **Google Photos** | `https://lh3.googleusercontent.com/...` | Direct image links only (not album pages) |
| **Dropbox** | `https://www.dropbox.com/s/{token}/photo.jpg?dl=0` | Automatically sets `dl=1` for direct download |
| **OneDrive** | `https://1drv.ms/{code}` | Short share links |
| **OneDrive** | `https://onedrive.live.com/...?resid={ID}` | Full share links |
| **Imgur** | `https://imgur.com/{ID}` | Single image pages (not albums or galleries) |
| **Box** | `https://app.box.com/s/{token}` | Shared file links |
| **PostImg** | `https://postimg.cc/{ID}` | Single image pages |
| **ImageBB** | `https://ibb.co/{ID}` | Single image pages |

### How to Get Direct Links

**Google Drive:**
1. Right-click the image in Google Drive
2. Click "Share" > "Copy link"
3. Make sure it's set to "Anyone with the link"
4. Paste the link directly into the CSV

**Dropbox:**
1. Right-click the file > "Copy link"
2. Paste directly — the `?dl=0` is auto-converted to `?dl=1`

**OneDrive:**
1. Right-click the file > "Share" > "Copy link"
2. Paste the short `1drv.ms` link directly

**Google Photos:**
1. Open the photo
2. Right-click the image > "Copy image address"
3. This gives you a direct `lh3.googleusercontent.com` URL

## URLs That Won't Work

These URLs serve HTML pages, not image files, and will fail gracefully:

| URL Type | Why It Fails |
|----------|-------------|
| Google Photos album pages (`photos.google.com/share/...`) | Returns HTML, not an image |
| Instagram post links | Requires authentication |
| Pinterest pins | Requires JavaScript to load |
| Facebook photo links | Requires authentication |
| Flickr pages | Requires API key for direct access |
| iCloud Photos links | Requires Apple authentication |
| Amazon Photos links | Requires authentication |

**When a URL fails**, the art piece is still created successfully — only the image attachment is skipped. The import results will show a warning message like "(image failed: reason)" next to that row.

## CSV Template Example

```csv
art_id,title,artist,medium,dimensions,description,starting_bid,tier,image_url
ART-001,Sunset Over Mountains,Jane Doe,Oil on Canvas,24 x 36 in,A vibrant sunset landscape,150.00,2,https://drive.google.com/file/d/15WchEp3cMaYcTLL0ZFoTfzmAe52ayhvs/view?usp=drive_link
ART-002,Ocean Waves,John Smith,Acrylic,18 x 24 in,Crashing waves at dusk,200.00,3,https://i.imgur.com/abc123.jpg|https://i.imgur.com/def456.jpg
ART-003,Still Life,Mary Johnson,Watercolor,12 x 16 in,Flowers in a vase,75.00,1,
```

- Row 2 imports **two images** — the first becomes the primary, the second is an additional image
- Row 3 has an empty `image_url` — the art piece will be created without an image. You can add images manually later from the edit page.
- Use `|` (pipe) to separate multiple image URLs within the same cell
