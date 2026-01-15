# Art in Heaven - Build Notes

## Asset Optimization

For production deployment, minify the following files:

### CSS Files
- `assets/css/frontend.css` → `assets/css/frontend.min.css`
- `assets/css/admin.css` → `assets/css/admin.min.css`

### JavaScript Files
- `assets/js/frontend.js` → `assets/js/frontend.min.js`
- `assets/js/admin.js` → `assets/js/admin.min.js`

## Recommended Minification Tools

1. **Using npm/Node.js:**
   ```bash
   npm install -g terser csso-cli
   
   # Minify JS
   terser assets/js/frontend.js -o assets/js/frontend.min.js -c -m
   terser assets/js/admin.js -o assets/js/admin.min.js -c -m
   
   # Minify CSS
   csso assets/css/frontend.css -o assets/css/frontend.min.css
   csso assets/css/admin.css -o assets/css/admin.min.css
   ```

2. **Using WordPress plugins:**
   - Autoptimize
   - W3 Total Cache
   - WP Rocket

3. **Online tools:**
   - https://minifier.org/
   - https://www.toptal.com/developers/javascript-minifier

## CDN Recommendations

For best performance, serve static assets via CDN:
- Cloudflare
- AWS CloudFront
- BunnyCDN

## Version History

- 2.7.0: Added Security, Cache, Notifications, Export, REST API classes
- 2.6.0: Added Registrants table, fixed double notifications
- 2.5.0: Added role-based access control
