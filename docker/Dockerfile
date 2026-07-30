# Turnkey PrestaShop 8.2.3 image with the combinationdescriptions module baked in.
#
# The official PrestaShop image already contains a full shop under
# /var/www/html and auto-installs it on first boot (PS_INSTALL_AUTO=1). We copy
# the module into its modules/ directory so it ships inside the image; a
# companion "module-installer" service (see docker-compose.yml) enables it once
# the shop finishes installing.
FROM prestashop/prestashop:8.2.3

# The module source (build context is the repository root).
COPY combinationdescriptions /var/www/html/modules/combinationdescriptions

# The helper that waits for PrestaShop to finish installing, then enables the
# module. Used by the module-installer service; harmless in the web service.
COPY docker/install-module.sh /usr/local/bin/install-module.sh

RUN chmod +x /usr/local/bin/install-module.sh \
    && chown -R www-data:www-data /var/www/html/modules/combinationdescriptions
