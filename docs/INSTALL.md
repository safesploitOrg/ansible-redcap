# Install

The install workflow provisions the LAMP dependencies, resolves a REDCap install ZIP, extracts it, configures `database.php`, imports the REDCap-generated SQL, applies permissions and validates the result.
It also creates `redcap_admin_username` as a REDCap superuser and sets `redcap_admin_password` using REDCap's own password hashing code.

## Required Inputs

- `redcap_allow_install=true`
- `redcap_artifact_provider`
- `redcap_install_version` or `redcap_install_artifact_url`
- database and REDCap secrets, preferably from Ansible Vault

For private lab use with the repository layout described in `ARCHITECTURE.md`, the default local provider reads install ZIPs from `../../artefacts/install` relative to `playbooks/`.

## Example

```bash
cd ansible-redcap
ansible-playbook playbooks/redcap-install.yml \
  -e redcap_allow_install=true \
  -e redcap_install_version=17.2.1
```

Use `redcap_install_version=latest` to select the highest semantic version found in the configured artifact directory.

## Secrets

Set these with Ansible Vault for repeatable runs:

- `redcap_mysql_root_password`
- `redcap_db_password`
- `redcap_db_salt`
- `redcap_admin_password`

Disposable lab runs can set `redcap_generate_missing_secrets=true`, but repeated runs without saved secrets may rotate values unexpectedly.
