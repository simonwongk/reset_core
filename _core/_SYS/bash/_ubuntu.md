```bash
crontab -e

PATH=/usr/local/lsws/lsphp71/bin:/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/root/.local/bin:/root/bin

0 3 * * * /usr/local/lsws/www/html/my.et.com/admin/.dbbk.sh > /tmp/dbbk.log 2>&1
10 20 * * * php /usr/local/lsws/www/html/my.et.com/admin/cron/oa.cl.expired.php > /dev/null
0 0,12 * * * python -c 'import random; import time; time.sleep(random.random() * 3600)' && ~/certbot-auto renew

```