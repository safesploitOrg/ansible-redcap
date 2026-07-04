# Ansible REDCap

Public-safe Ansible automation for deploying, upgrading, validating and operating a REDCap-style LAMP stack.

This directory is designed to be developed inside `ansible-redcap-private` while real licensed REDCap ZIP packages remain outside the public-safe automation tree.

## 📁 Repository Layout

```text
playbooks/              Install, upgrade, validate and rollback entry points
roles/                  Modular implementation of the REDCap workflows
scripts/                Helper tooling that is safe to publish
inventories/staging/    Example inventory and variables
docs/                   Operator documentation
```

## 🧭 Quick Start

### Ansible Galaxy

Install required collections:

```bash
ansible-galaxy collection install -r requirements.yml
```

### Ansible Syntax Check

Run a syntax check:

```bash
ansible-playbook playbooks/redcap-install.yml --syntax-check
ansible-playbook playbooks/redcap-upgrade.yml --syntax-check
```

### 🧾 Inventory

Update the inventory as necessary:

```bash
vi inventories/staging/hosts.yml
```

---

### 🚀 1. Install REDCap

For a private lab install using local artifacts from the parent private repository:

#### Latest version

```bash
ansible-playbook playbooks/redcap-install.yml \
  -i inventories/staging/hosts.yml \
  -e redcap_allow_install=true \
  -e redcap_install_version=latest
```

#### Specific version

```bash
ansible-playbook playbooks/redcap-install.yml \
  -i inventories/staging/hosts.yml \
  -e redcap_allow_install=true \
  -e redcap_install_version=17.0.0
```

The default artifact path is `{{ playbook_dir }}/../../artefacts`, which resolves to the private repository's `artefacts/` directory when this project is kept in `ansible-redcap-private/ansible-redcap/`.

---

### ⬆️ 2. Upgrade REDCap

> ⚠️ By default `redcap-upgrade.yml` will create a REDCap backup at `/root/` on the remote-host
> Beaware this could exhaust disk-space

- Latest version: `redcap_upgrade_version=latest`
- Specific version (e.g.): `redcap_upgrade_version=17.1.0`

```bash
ansible-playbook playbooks/redcap-upgrade.yml \
  -i inventories/staging/hosts.yml \
  -e redcap_allow_upgrade=true \
  -e redcap_upgrade_version=latest
```

### 🧪 3. Validate REDCap (after install or upgrade)

```bash
ansible-playbook playbooks/redcap-validate.yml \
  -i inventories/staging/hosts.yml
```

### ♻️ 4. Rollback REDCap (Restore)

```bash
ansible-playbook playbooks/redcap-rollback.yml \
  -i inventories/staging/hosts.yml \
  -e redcap_allow_rollback=true \
  -e redcap_restore_db_dump=/root/redcap-redcap_lab-2026-07-04_14-01-23.sql \
  -e redcap_restore_web_archive=/root/redcap-redcap_lab-2026-07-04_14-01-23-web.tar.gz
```

---

## 🐳 Docker Lab

<details>
<summary>
Toggle Me!
</summary>

> ⚠️ Currently only in the private repo

From the private repository root:

```bash
docker/build.sh
docker/up.sh
docker/test.sh smoke
docker/test.sh artifact
docker/test.sh install
docker/test.sh validate
docker/reset.sh
```

The lab container runs AlmaLinux 10 with systemd and mounts the repository at `/workspace`, allowing the playbooks to install services and consume the private `artefacts/` directory without copying licensed ZIPs into this automation tree.
Docker playbook runs use `docker/inventory.yml` with a local connection, keeping VM inventory changes isolated from container tests.
The default exposed ports are `18080` and `18443`; override them with `REDCAP_DOCKER_HTTP_PORT` and `REDCAP_DOCKER_HTTPS_PORT`.
Use `docker/reset.sh` to remove the lab container and volumes before testing a fresh install.

</details>

## 🔐 Safety Guardrails

The install and upgrade playbooks refuse to run until the matching guard variable is enabled:

- `redcap_allow_install=true`
- `redcap_allow_upgrade=true`
- `redcap_allow_rollback=true`

Production-targeted runs also require `redcap_allow_production=true`.

Real secrets should be supplied through Ansible Vault or CI secrets. Lab runs can generate missing secrets, but generated values are not persisted outside the optional root-owned install details file.
