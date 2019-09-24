# eBay Email Notifications Reader

This script is meant to be run as a cron job that reads an email address looking for eBay and HipStamp notifications.
It will then shoot off an event to a specified URL.

You will need a .env like so:

```
IMAP_SERVER={yoursever.com:993/imap/ssl}INBOX
IMAP_UNAME=your_user_name
IMAP_PWORD="your_user_password"
IMAP_ENCODING=UTF-8
ON_SOLD="https://yourdomain.com?action=ebay-notification&status=sold&ebay_id=%ebay_id&sku=%sku"
ON_OFFER="https://yourdomain.com?action=ebay-notification&status=sold&ebay_id=%ebay_id&sku=%sku"
```

