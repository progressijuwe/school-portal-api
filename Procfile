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

# The single process Railway starts. It launches supervisor, which runs the web
# server, the queue worker and the scheduler together — see supervisord.conf.
# Kept identical to the `[start]` command in nixpacks.toml so it does not matter
# which of the two the builder decides to honour.
#
# NOTE: `artisan serve` (inside supervisord.conf) wraps PHP's built-in server,
# which handles one request at a time. It is fine for a pilot or a small cohort,
# but it will queue requests under real registration-week load. Before going
# wide, switch that program to FrankenPHP
# (`php artisan octane:frankenphp --host=0.0.0.0 --port=$PORT`) or a
# php-fpm + nginx image.
web: supervisord -c /app/supervisord.conf

# The two commands below are what supervisord runs. They are named here so that
# splitting them into their own Railway services later is a matter of pointing a
# new service at this repo and overriding its start command — at which point
# drop them from supervisord.conf so they do not run twice.
#
# Queued work: welcome emails, grade notifications, and GPA recomputation.
# Without a worker running, every queued job sits in the jobs table forever —
# students never receive credentials and CGPA never updates after an approval.
worker: php artisan queue:work --tries=3 --max-time=3600 --sleep=3

# Scheduled maintenance (token pruning, failed-job cleanup).
scheduler: php artisan schedule:work
