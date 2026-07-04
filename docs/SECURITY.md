# Security

## License Boundary

Do not commit REDCap ZIP files, extracted REDCap source, real credentials, salts, production hostnames or institutional configuration into this public-safe automation tree.

## Secrets

Use Ansible Vault or CI secrets for:

- `redcap_mysql_root_password`
- `redcap_db_password`
- `redcap_db_salt`
- `redcap_admin_password`
- private artifact repository credentials

Tasks that handle generated or supplied credentials use `no_log` where practical.

## Guardrails

Install, upgrade and rollback playbooks require explicit opt-in variables. Production-targeted runs require a second opt-in with `redcap_allow_production=true`.

## File Permissions

The default REDCap web tree is owned by `root:apache`, with writable runtime directories set to `0770`. The install details file is written under `/root/.redcap` with `0600` permissions when enabled.
