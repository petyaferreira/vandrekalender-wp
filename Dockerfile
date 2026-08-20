FROM wordpress:php8.4-apache

RUN apt-get update && apt-get install -y sudo less default-mysql-client nano vim unzip

RUN curl -o /bin/wp-cli.phar https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
COPY wp-su.sh /bin/wp
RUN chmod +x /bin/wp-cli.phar /bin/wp

# Local email testing. Mailpit ships a sendmail-compatible binary, so pointing
# PHP's sendmail_path at it makes plain mail() — and therefore every wp_mail()
# call — deliver into the Mailpit container instead of the internet. Configured
# here rather than in WordPress on purpose: no SMTP plugin to activate, no
# settings in the database to be wiped by the next prod import, nothing that can
# leak to production (deploys only rsync wp-content/themes and plugins).
COPY --from=axllent/mailpit:latest /mailpit /usr/local/bin/mailpit
RUN printf 'sendmail_path = "/usr/local/bin/mailpit sendmail -S mailpit:1025 -t -i"\n' \
    > /usr/local/etc/php/conf.d/mailpit.ini

# The official WordPress image bundles two plugins we don't use — Hello Dolly
# (hello.php) and Akismet. On every start the entrypoint seeds wp-content from
# this pristine copy at /usr/src/wordpress into the (bind-mounted) document root,
# copying only files that don't already exist — which is why deleting them from
# wp-admin or disk never sticks: the next start copies them straight back.
# Removing them from the seed source here means the entrypoint has nothing to
# copy, so they never reappear — without overriding the container command, which
# would disable the entrypoint's core-copy and leave core missing after a
# `docker compose down` (it removes the anonymous /var/www/html volume).
RUN rm -f /usr/src/wordpress/wp-content/plugins/hello.php \
 && rm -rf /usr/src/wordpress/wp-content/plugins/akismet

# Same story for the bundled default themes. The image ships twentytwentythree,
# -four and -five; we only use -five (installed via Composer/wpackagist). The
# entrypoint would otherwise re-seed all three into the bind-mounted themes dir
# on every start — which is why they kept reappearing after `./start.sh` even
# though the Composer `remove-default-themes` script deletes them at install
# time. Stripping them from the seed source here means there is nothing to copy.
RUN rm -rf /usr/src/wordpress/wp-content/themes/twentytwentythree \
 && rm -rf /usr/src/wordpress/wp-content/themes/twentytwentyfour

RUN apt-get clean
RUN rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*
