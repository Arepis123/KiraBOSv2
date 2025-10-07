# KiraBOS v1.0.0 - Production Deployment Guide

## Pre-Deployment Checklist

### 1. Database Configuration
- [ ] Update `config.php` with production database credentials
- [ ] Ensure `$isProduction` flag is correctly set in `config.php`
- [ ] Run all database migrations in order
- [ ] Backup existing database before deployment

### 2. Server Requirements
- [ ] PHP 7.4 or higher
- [ ] MySQL 5.7 or higher
- [ ] Apache with mod_rewrite enabled
- [ ] HTTPS enabled (required for Service Workers)
- [ ] `uploads/products/` directory writable (755 permissions)
- [ ] `logs/` directory writable (755 permissions)

### 3. Security
- [ ] Change default admin/cashier passwords
- [ ] Verify CSRF token validation is enabled
- [ ] Check file upload restrictions
- [ ] Review session timeout settings
- [ ] Enable error logging to file (not display)

### 4. Performance
- [ ] Enable PHP OPcache
- [ ] Set appropriate cache headers in Apache
- [ ] Optimize database indexes
- [ ] Test with multiple concurrent users

### 5. PWA/Offline Features
- [ ] Test Service Worker registration on HTTPS
- [ ] Verify offline order sync functionality
- [ ] Test on multiple devices (desktop, mobile, tablet)
- [ ] Check network status indicator
- [ ] Verify IndexedDB storage limits

## Deployment Steps

### Step 1: Upload Files
```bash
# Upload all files to production server
rsync -avz --exclude='.git' --exclude='logs' --exclude='nul' ./ user@server:/path/to/webroot/
```

### Step 2: Set Permissions
```bash
chmod 755 uploads/products/
chmod 755 logs/
chmod 644 *.php
chmod 644 sw.js
chmod 644 manifest.json
```

### Step 3: Database Setup
```bash
# Import database or run migrations
mysql -u username -p database_name < KiraBOSv2_MultiTenant.sql
```

### Step 4: Configure Environment
- Update `config.php` with production settings
- Set `$isProduction = true` or detect via hostname
- Update database credentials

### Step 5: Test
1. Access via HTTPS (Service Worker requires HTTPS)
2. Test login with admin and cashier accounts
3. Test creating orders online
4. Test offline functionality (DevTools → Network → Offline)
5. Test order sync when coming back online
6. Check error logs in `logs/` directory

## Post-Deployment

### Monitoring
- Check `logs/js-errors-*.log` for JavaScript errors
- Monitor `logs/` directory for application errors
- Set up log rotation for error logs

### Maintenance
- Regular database backups
- Clear old synced orders from IndexedDB (automatic)
- Update cache version in `sw.js` when deploying updates
- Monitor disk space for uploads and logs

## Troubleshooting

### Service Worker Not Registering
- Ensure HTTPS is enabled
- Check browser console for errors
- Verify `sw.js` is accessible
- Clear browser cache and hard refresh

### Offline Sync Not Working
- Check IndexedDB in DevTools → Application
- Verify network events are firing
- Check `logs/js-errors-*.log` for errors
- Test manual sync by clicking network status badge

### Database Connection Errors
- Verify credentials in `config.php`
- Check MySQL user permissions
- Ensure database exists
- Check firewall rules

## Rollback Plan
1. Keep backup of previous version
2. Keep database backup
3. If issues occur:
   ```bash
   # Restore files
   rsync -avz /backup/path/ /production/path/

   # Restore database
   mysql -u username -p database_name < backup.sql
   ```

## Version History
- v1.0.0 - Initial production release with PWA offline support

## Support
For issues or questions, check the documentation in `CLAUDE.md`
