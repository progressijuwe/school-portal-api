# Railway start command: web server, queue worker and scheduler in one container.
#
# Railway runs exactly one process per service, and Nixpacks only ever runs the
# `web` command. Getting a worker and a scheduler any other way means creating
# two more services by hand in the dashboard — so a deploy that came purely from
# merging into main would have neither, and every queued job would sit in the
# `jobs` table forever: no account credentials delivered, no grade
# notifications, and a CGPA that never moves after an approval.
#
# This deliberately uses nothing but `sh` and `php`. An earlier version drove
# the three processes with supervisord, which failed on deploy with
# "supervisord: command not found" — the `supervisor` Nix package is available
# during the build but is not on PATH in the runtime image.
#
# Invoked as `sh start.sh`, never `./start.sh`: the repository is committed from
# Windows, which does not carry a Unix executable bit, so relying on one would
# break the deploy the first time the file was checked out fresh.
#
# The trade-off is that web, worker and scheduler share this container's CPU and
# memory, and scaling the web service scales the worker with it. That is fine at
# pilot size. Before going wide, split them into their own Railway services (the
# Procfile still names the commands) and cut this back to the exec line alone.

PORT="${PORT:-8000}"

# `queue:work --max-time` exits after an hour on purpose, so that a long-lived
# worker cannot hold a stale copy of the application in memory. That means it
# has to be restarted, which is what the loop is for; without it the worker
# would stop an hour after each deploy and queued jobs would silently stall.
# `|| true` keeps a crash from ending the loop.
while true; do
	php artisan queue:work --tries=3 --max-time=3600 --sleep=3 || true
	sleep 2
done &

while true; do
	php artisan schedule:work || true
	sleep 2
done &

# exec, so the web server replaces this shell as the container's main process.
# Railway's health checks and stop signals then reach the process that actually
# serves traffic, and a failure in either background loop above leaves the site
# up rather than taking the whole container down with it.
exec php artisan serve --host=0.0.0.0 --port="$PORT"
