# Operations

## Validation

Run the validation playbook after install, upgrade or manual maintenance:

```bash
ansible-playbook playbooks/redcap-validate.yml
```

Validation checks service state, required paths, the local HTTP endpoint and a simple MariaDB query.

## Rollback

Rollback is intentionally guarded and requires explicit backup paths:

```bash
ansible-playbook playbooks/redcap-rollback.yml \
  -e redcap_allow_rollback=true \
  -e redcap_restore_db_dump=/root/redcap-example-2026-07-03.sql \
  -e redcap_restore_web_archive=/root/redcap-example-2026-07-03-web.tar.gz
```

The rollback playbook restores the web archive, imports the database dump, restarts services and runs validation.

## Artifact Selection

The `redcap_artifact` role supports:

- `local`: copy a ZIP from the controller workspace
- `filesystem`: copy a ZIP already available on the target
- `url`: download a ZIP from HTTP or HTTPS

The `latest` selector parses versions numerically from REDCap filenames, so `17.10.0` sorts higher than `17.2.1`.
