# Railway process definitions.
#
# `release` runs once per deploy, before the new version takes traffic. Running
# migrations here rather than in the web start command means they execute
# exactly once no matter how many web replicas are running, and a failed
# migration aborts the deploy instead of leaving replicas on mismatched schemas.
#
# --force is required because Laravel refuses to migrate a production database
# interactively; --isolated takes an advisory lock so concurrent deploys cannot
# both run the same migration.
release: php artisan migrate --force --isolated && php artisan config:cache && php artisan route:cache && php artisan event:cache

# NOTE: `artisan serve` wraps PHP's built-in server, which handles one request
# at a time. It is fine for a pilot or a small cohort, but it will queue
# requests under real registration-week load. Before going wide, switch this to
# FrankenPHP (`php artisan octane:frankenphp --host=0.0.0.0 --port=$PORT`) or a
# php-fpm + nginx image.
web: php artisan serve --host=0.0.0.0 --port=${PORT:-8000}

# Queued work: welcome emails, grade notifications, and GPA recomputation.
#
# This must run as a SEPARATE Railway service pointing at the same repo, with
# its start command overridden to this line. Without a worker running, every
# queued job sits in the jobs table forever — students never receive
# credentials and CGPA never updates after an approval.
worker: php artisan queue:work --tries=3 --max-time=3600 --sleep=3

# Scheduled maintenance (token pruning, failed-job cleanup). Also a separate
# service, or a Railway cron hitting `php artisan schedule:run` every minute.
scheduler: php artisan schedule:work
