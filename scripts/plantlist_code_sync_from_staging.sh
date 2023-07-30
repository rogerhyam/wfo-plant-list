# FOR USE ON LIVE SERVER
# This script should be copied to /var/wfo-list and run from there
# It will be used when it, and the rest of the code, doesn't exist.
# it also needs to be in the same place as the other sync_from and sync_to scripts
rsync -Pav -e "ssh -i ~/.ssh/wfo-aws-03.pem" --delete --exclude 'www/matching_cache' --exclude '.git'  wfo@wfo-staging.rbge.info:/var/wfo-list/plantlist /var/wfo-list/
mkdir /var/wfo-list/plantlist/www/matching_cache  # on first install this needs to be chown'd to allow www-data write access
