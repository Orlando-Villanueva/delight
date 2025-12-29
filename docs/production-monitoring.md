# Production Monitoring Guide

This document outlines all available monitoring capabilities for Delight in production and provides essential commands for checking system health.

## 🖥️ Server & Infrastructure Monitoring

### **Laravel Cloud Dashboard**
- **Access**: Laravel Cloud dashboard → Your Project
- **Monitors**: CPU, Memory, Disk usage, Load average
- **Alerts**: Automatic notifications for server issues

### **Key Server Metrics**
```bash
# SSH into production server (if applicable)
ssh your-user@your-server-ip

# Check server resources
htop                    # CPU and memory usage
df -h                   # Disk space usage
free -h                 # Memory usage details
uptime                  # Server uptime and load
```

## 📧 Email System Monitoring

### **Mailgun Dashboard**
- **Access**: [mailgun.com](https://mailgun.com) → Domains → mg.mydelight.app
- **Monitors**: Email delivery rates, bounces, complaints, opens
- **Stats**: Daily/monthly sending volume and success rates

### **Queue System Health**
```bash
# Check queue worker status
sudo supervisorctl status

# Monitor queue jobs
php artisan queue:monitor

# Check failed jobs
php artisan queue:failed

# View recent failed jobs with details
php artisan queue:failed --verbose

# Retry failed jobs
php artisan queue:retry all

# Clear failed jobs
php artisan queue:flush
```

### **Email Delivery Testing**
```bash
# Test welcome email system
php artisan test:welcome-notification --email=your-email@gmail.com

# Check if jobs are being created
php artisan tinker
>>> DB::table('jobs')->count()
>>> DB::table('jobs')->get()
>>> exit
```

## 📊 Application Monitoring

### **Laravel Logs**
```bash
# View recent application logs (live tail)
tail -f storage/logs/laravel.log

# View last 50 lines of logs
tail -50 storage/logs/laravel.log

# Search for specific errors
grep -i "error\|exception\|fail" storage/logs/laravel.log | tail -20

# Check for email-related issues
grep -i "mail\|notification\|welcome" storage/logs/laravel.log | tail -10

# View logs from specific date
grep "2025-07-31" storage/logs/laravel.log

# Check if log file exists and permissions
ls -la storage/logs/laravel.log
```

### **Database Health**
```bash
# Check database connection
php artisan tinker
>>> DB::connection()->getPdo()
>>> exit

# View recent user registrations
php artisan tinker
>>> User::latest()->take(10)->get(['id', 'name', 'email', 'created_at'])
>>> exit

# Check queue jobs table
php artisan tinker
>>> DB::table('jobs')->count()
>>> DB::table('failed_jobs')->count()
>>> exit
```

### **Application Performance**
```bash
# Check application status
php artisan about

# Clear various caches if needed
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Optimize for production
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## 🚨 Common Issue Diagnostics

### **Welcome Emails Not Sending**
```bash
# 1. Check if queue worker is running
sudo supervisorctl status

# 2. Check for failed jobs
php artisan queue:failed

# 3. Test Mailgun connection
curl -s --user 'api:your-mailgun-key' \
    https://api.mailgun.net/v3/mg.mydelight.app/messages \
    -F from='noreply@mg.mydelight.app' \
    -F to='test@example.com' \
    -F subject='Test' \
    -F text='Test'

# 4. Check Laravel logs for errors
grep -i "mailgun\|notification\|welcome" storage/logs/laravel.log | tail -10
```

### **High Server Load**
```bash
# Check what's using resources
htop
ps aux --sort=-%cpu | head -10    # Top CPU processes
ps aux --sort=-%mem | head -10    # Top memory processes

# Check disk usage
df -h
du -sh /var/www/mydelight.app/* | sort -hr
```

### **Database Issues**
```bash
# Check database connection
php artisan tinker
>>> DB::select('SELECT 1')
>>> exit

# Check for long-running queries (if using MySQL)
php artisan tinker
>>> DB::select('SHOW PROCESSLIST')
>>> exit
```

## 📈 Daily Health Check Routine

### **Morning Check (2 minutes)**
```bash
# 1. Server health
uptime && free -h

# 2. Queue status
sudo supervisorctl status
php artisan queue:failed

# 3. Recent logs
tail -20 storage/logs/laravel.log

# 4. Test email system
php artisan test:welcome-notification --email=your-email@gmail.com
```

### **Weekly Deep Check (10 minutes)**
```bash
# 1. Disk space trends
df -h

# 2. Failed jobs analysis
php artisan queue:failed --verbose

# 3. User registration trends
php artisan tinker
>>> User::whereDate('created_at', '>=', now()->subWeek())->count()
>>> exit

# 4. Email delivery stats (check Mailgun dashboard)

# 5. Server resource trends (check Laravel Cloud dashboard)
```

## 🔔 Alert Setup Recommendations

### **Laravel Cloud Alerts (If Available)**
- Server down alerts
- High CPU/memory usage
- Disk space warnings
- Queue worker failures

### **Mailgun Alerts (Configure in Dashboard)**
- High bounce rates
- Spam complaints
- Delivery failures

### **Custom Monitoring (Optional)**
```bash
# Create a simple health check endpoint
# Add to routes/web.php:
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'database' => DB::connection()->getPdo() ? 'connected' : 'disconnected',
        'queue' => DB::table('jobs')->count(),
        'failed_jobs' => DB::table('failed_jobs')->count(),
    ]);
});

# Then monitor with external service like UptimeRobot
```

## 🎯 Key Performance Indicators (KPIs)

### **User Growth**
```bash
# Daily registrations
php artisan tinker
>>> User::whereDate('created_at', today())->count()

# Weekly registrations  
>>> User::whereDate('created_at', '>=', now()->subWeek())->count()

# Total users
>>> User::count()
>>> exit
```

### **Email System Health**
- **Queue jobs processed**: Should be 0 or low
- **Failed jobs**: Should be 0
- **Mailgun delivery rate**: Should be >95%
- **Welcome email delivery time**: Should be <1 minute

### **System Performance**
- **Server load**: Should be <2.0
- **Memory usage**: Should be <80%
- **Disk usage**: Should be <80%
- **Response time**: Should be <500ms

## 📞 Emergency Contacts & Resources

### **Service Dashboards**
- **Laravel Cloud**: Laravel Cloud dashboard
- **Mailgun**: [mailgun.com](https://mailgun.com)
- **Domain DNS**: Your domain registrar

### **Quick Recovery Commands**
```bash
# Restart queue workers
php artisan queue:restart

# Restart web server
sudo service nginx restart
sudo service php8.3-fpm restart

# Clear all caches
php artisan optimize:clear

# Emergency maintenance mode
php artisan down --secret=your-secret-token
php artisan up
```

---

**Last Updated**: July 31, 2025  
**Environment**: Production (mydelight.app)  
**Monitoring Level**: MVP Basic (Sufficient for launch)
