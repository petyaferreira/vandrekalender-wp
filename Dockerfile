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

RUN apt-get clean
RUN rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*
