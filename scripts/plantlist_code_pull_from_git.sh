# FOR USE ON STAGING SERVER
# This script should be copied to /var/wfo-list and run from there
# It will be used when it, and the rest of the code, doesn't exist.
# it also needs to be in the same place as the other sync_from and sync_to scripts
mkdir -p /var/wfo-list/plantlist
cd /var/wfo-list/plantlist
git stash
git pull

# we make sure the matching cache dir exists but the permissions
# for this need to be changed to allow www-data to write on first install
mkdir /var/wfo-list/plantlist/www/matching_cache 
