# Install crontab files
if [[ -d "/opt/docker/etc/cron" ]]; then
    mkdir -p /etc/crontabs/

    find /opt/docker/etc/cron -type f -name "root" | while read CRONTAB_FILE; do
        DEST_FILE="/etc/crontabs/root"

        # Prepend our cron entries to the existing root crontab
        if [[ -f "$DEST_FILE" ]]; then
            # Create temp file with our entries + existing entries
            cat "$CRONTAB_FILE" > "${DEST_FILE}.new"
            echo "" >> "${DEST_FILE}.new"
            cat "$DEST_FILE" >> "${DEST_FILE}.new"
            mv "${DEST_FILE}.new" "$DEST_FILE"
        else
            cp -a -- "$CRONTAB_FILE" "$DEST_FILE"
        fi

        # Fix permissions: BusyBox crond requires 0600 for /etc/crontabs/
        chmod 0600 -- "$DEST_FILE"
        chown root:root -- "$DEST_FILE"
    done
fi
