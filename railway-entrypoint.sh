#!/bin/sh
# Starts Apache on Railway. Railway can end up loading a second Apache MPM
# ("AH00534: More than one MPM loaded"); mod_php needs exactly one: prefork.

echo "=== MPM files enabled BEFORE fix ==="
ls -la /etc/apache2/mods-enabled 2>/dev/null | grep -i mpm
echo "=== config files mentioning an MPM ==="
grep -rIl "mpm_" /etc/apache2 2>/dev/null

# 1) Disable event/worker the normal way, then by force-deleting the links
for m in mpm_event mpm_worker; do
  a2dismod -f "$m" >/dev/null 2>&1
  rm -f "/etc/apache2/mods-enabled/$m.load" "/etc/apache2/mods-enabled/$m.conf"
done

# 2) Comment out any stray LoadModule line for event/worker in any config file
grep -rIl "LoadModule mpm_\(event\|worker\)_module" /etc/apache2 2>/dev/null \
  | xargs -r sed -i 's/^\(\s*LoadModule mpm_\(event\|worker\)_module.*\)/# \1/'

# 3) Make sure prefork is the one that's on
a2enmod mpm_prefork >/dev/null 2>&1

echo "=== MPM files enabled AFTER fix ==="
ls -la /etc/apache2/mods-enabled 2>/dev/null | grep -i mpm
echo "=== Apache loaded modules (mpm) ==="
apache2ctl -M 2>&1 | grep -i mpm

# 4) Listen on the port Railway assigns
sed -i "s/Listen 80/Listen ${PORT:-80}/" /etc/apache2/ports.conf
sed -i "s/:80>/:${PORT:-80}>/" /etc/apache2/sites-available/000-default.conf

exec apache2-foreground
