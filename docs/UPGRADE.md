# Upgrade

The upgrade workflow validates an existing REDCap installation, places REDCap into maintenance mode, resolves an upgrade ZIP, takes backups, extracts the upgrade, restores `database.php`, imports the REDCap-generated upgrade SQL, reapplies permissions, validates the result and then disables maintenance mode.

## Required Inputs

- `redcap_allow_upgrade=true`
- `redcap_upgrade_version` or `redcap_upgrade_artifact_url`
- a valid existing `redcap_web_root`
- a valid existing `redcap_database_php_path`

## Example

```bash
cd ansible-redcap
ansible-playbook playbooks/redcap-upgrade.yml \
  -e redcap_allow_upgrade=true \
  -e redcap_upgrade_version=17.2.1
```

Use `redcap_upgrade_version=latest` to select the highest semantic version found in the configured upgrade artifact directory.

## Backups

Before extraction, the role writes:

- database dump: `/root/redcap-<db>-<date>.sql`
- web archive: `/root/redcap-<db>-<date>-web.tar.gz`
- data archive: `/root/redcap-<db>-<date>-data.tar.gz`

The data backup is required by default. Set `redcap_backup_data_required=false` only for test systems where the data directory is intentionally absent.

## Maintenance Mode

Upgrade maintenance mode is enabled by default. Override these variables if needed:

- `redcap_maintenance_during_upgrade=false`
- `redcap_maintenance_restore_after_upgrade=false`
- `redcap_maintenance_message="Custom message"`
