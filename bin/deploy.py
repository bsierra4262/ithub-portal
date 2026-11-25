#!/usr/bin/env python3
"""
Despliegue de ITHub a Hostinger por SSH.

Todo comando necesita el destino, escrito a proposito: --produccion (o --pruebas
cuando exista esa instalacion).

    python bin/deploy.py app --produccion          sube app/web/ al webroot (con swap)
    python bin/deploy.py app --produccion --volver vuelve a la version anterior
                                                   (las ultimas 5 quedan en ~/ithub_versiones)
    python bin/deploy.py config --produccion       sube app/server-config/config.php
                                                   a ~/ithub_config/config.php (600)
    python bin/deploy.py instalar --produccion     corre app/cli/instalar.php alla
                                                   (esquema, clave de firma, super admins, catalogo)
    python bin/deploy.py test --produccion         sube test/ y corre la bateria contra el
                                                   codigo desplegado
    python bin/deploy.py check --produccion        php -l de todo lo desplegado
    python bin/deploy.py run "<cmd>" --produccion  ejecuta un comando por SSH

El acceso SSH NO vive en el repositorio: se lee de variables de entorno o de
`bin/deploy.local.json` (ignorado por git).

    ITHUB_SSH_HOST · ITHUB_SSH_PORT · ITHUB_SSH_USER · ITHUB_SSH_KEY
"""
# ============================================================
#  Notas para quien lo usa o lo cambia:
#
#  · No hay destino por defecto: sin --produccion ni --pruebas el script corta.
#  · `app` NO sube encima de lo que esta en uso: arma ~/ithub_build/<fecha>,
#    extrae ahi, le pasa `php -l` a todo y recien entonces cambia las carpetas
#    con dos `mv` seguidos (mismo disco: instantaneo). Si algo falla, el sitio
#    sigue con lo que tenia. La version reemplazada queda en
#    ~/ithub_versiones/<destino>/<fecha> (las 5 ultimas) y `app --volver` la
#    pone de nuevo. Lo que hay en el servidor y no en el repositorio
#    (.user.ini, error_log) se conserva.
#  · Ningun archivo con pinta de secreto (config.local.php, *.pem, *.key,
#    *.sqlite, deploy.local.json) se sube con `app`, aunque este en la carpeta.
#  · Junto al codigo viaja una copia de app/db/schema.sql en app/cli/schema.sql
#    (negada por HTTP) para que `instalar` la encuentre.
# ============================================================
from __future__ import annotations

import fnmatch
import io
import json
import os
import subprocess
import sys
import tarfile
import time
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent

for _flujo in (sys.stdout, sys.stderr):
    try:
        _flujo.reconfigure(encoding="utf-8", errors="replace")
    except AttributeError:  # pragma: no cover
        pass

DESTINOS = {
    "produccion": {
        "webroot": "domains/ithub.intellihelp.tech/public_html",
        "config": "ithub_config/config.php",
    },
    "pruebas": {
        "webroot": "domains/ithub-pruebas.intellihelp.tech/public_html",
        "config": "ithub_config/pruebas.php",
    },
}
PHP = "/opt/alt/php83/usr/bin/php"
NO_SUBIR = ["config.local.php", "*.pem", "*.key", "*.sqlite", "*.sqlite3", "deploy.local.json", ".DS_Store", "Thumbs.db"]
CONSERVAR = [".user.ini", "error_log", ".well-known"]


def ayuda(msg: str = "") -> None:
    if msg:
        print(f"error: {msg}\n", file=sys.stderr)
    print(__doc__)
    sys.exit(2)


def acceso() -> list[str]:
    datos = {}
    local = RAIZ / "bin" / "deploy.local.json"
    if local.exists():
        datos = json.loads(local.read_text(encoding="utf-8"))
    host = os.environ.get("ITHUB_SSH_HOST", datos.get("host", ""))
    user = os.environ.get("ITHUB_SSH_USER", datos.get("user", ""))
    port = os.environ.get("ITHUB_SSH_PORT", str(datos.get("port", "22")))
    key = os.environ.get("ITHUB_SSH_KEY", datos.get("key", ""))
    if not host or not user:
        ayuda("falta el acceso SSH (ITHUB_SSH_HOST / ITHUB_SSH_USER o bin/deploy.local.json)")
    cmd = ["ssh", "-p", port, "-o", "BatchMode=yes"]
    if key:
        cmd += ["-i", os.path.expanduser(key)]
    return cmd + [f"{user}@{host}"]


def ssh_script(script: str, chequear: bool = True) -> int:
    r = subprocess.run(acceso() + ["bash -se"], input=script.encode())
    if chequear and r.returncode != 0:
        print(f"[deploy] fallo (codigo {r.returncode})", file=sys.stderr)
        sys.exit(r.returncode)
    return r.returncode


def ssh_con_entrada(comando: str, datos: bytes) -> None:
    r = subprocess.run(acceso() + [comando], input=datos)
    if r.returncode != 0:
        print(f"[deploy] fallo (codigo {r.returncode})", file=sys.stderr)
        sys.exit(r.returncode)


def excluido(nombre: str) -> bool:
    return any(fnmatch.fnmatch(nombre, p) for p in NO_SUBIR)


def tar_de(carpeta: Path, extra: dict[str, Path] | None = None) -> bytes:
    buf = io.BytesIO()
    with tarfile.open(fileobj=buf, mode="w:gz") as tar:
        for ruta in sorted(carpeta.rglob("*")):
            rel = ruta.relative_to(carpeta).as_posix()
            if ruta.is_dir() or excluido(ruta.name) or "__pycache__" in rel:
                continue
            tar.add(ruta, arcname=rel)
        for arc, ruta in (extra or {}).items():
            tar.add(ruta, arcname=arc)
    return buf.getvalue()


def cmd_app(dest: str, volver: bool) -> None:
    d = DESTINOS[dest]
    web = d["webroot"]
    vers = f"ithub_versiones/{dest}"
    if volver:
        ssh_script(f"""
set -e; cd ~
ultima=$(ls -1 {vers} 2>/dev/null | sort | tail -1)
[ -n "$ultima" ] || {{ echo "[deploy] no hay versiones guardadas"; exit 1; }}
mv {web} {vers}/$ultima.descartada-$(date +%Y%m%d-%H%M%S)
mv {vers}/$ultima {web}
echo "[deploy] volvio a la version $ultima"
""")
        return
    ts = time.strftime("%Y%m%d-%H%M%S")
    datos = tar_de(RAIZ / "app" / "web", {"app/cli/schema.sql": RAIZ / "app" / "db" / "schema.sql"})
    print(f"[deploy] subiendo {len(datos) // 1024} KB a ~/ithub_build/{ts}")
    ssh_con_entrada(f"mkdir -p ~/ithub_build/{ts} && tar -xzf - -C ~/ithub_build/{ts}", datos)
    conservar = " ".join(CONSERVAR)
    ssh_script(f"""
set -e; cd ~
b=ithub_build/{ts}
for f in {conservar}; do [ -e {web}/$f ] && cp -a {web}/$f $b/ || true; done
errores=$(find $b -name '*.php' -print0 | xargs -0 -n1 {PHP} -l 2>&1 | grep -v '^No syntax errors' || true)
if [ -n "$errores" ]; then echo "$errores"; echo "[deploy] errores de sintaxis: no se activa"; rm -rf $b; exit 1; fi
mkdir -p {vers}
[ -d {web} ] && mv {web} {vers}/{ts}
mv $b {web}
chmod 755 {web}
ls -1 {vers} | sort | head -n -5 | while read v; do rm -rf {vers}/$v; done
echo "[deploy] activo: {web} (anterior en {vers}/{ts})"
""")


def cmd_config(dest: str) -> None:
    origen = RAIZ / "app" / "server-config" / ("config.php" if dest == "produccion" else "pruebas.php")
    if not origen.exists():
        ayuda(f"no existe {origen.relative_to(RAIZ)}")
    destino = DESTINOS[dest]["config"]
    ssh_con_entrada(f"mkdir -p ~/ithub_config && chmod 700 ~/ithub_config && umask 077 && cat > ~/{destino} && chmod 600 ~/{destino}",
                    origen.read_bytes())
    print(f"[deploy] configuracion subida a ~/{destino} (600)")


def cmd_instalar(dest: str) -> None:
    ssh_script(f"cd ~ && {PHP} {DESTINOS[dest]['webroot']}/app/cli/instalar.php")


def cmd_test(dest: str) -> None:
    ssh_con_entrada("rm -rf ~/ithub_test && mkdir -p ~/ithub_test && tar -xzf - -C ~/ithub_test",
                    tar_de(RAIZ / "test"))
    ssh_script(f"cd ~/ithub_test && ITHUB_SRC=$HOME/{DESTINOS[dest]['webroot']}/app {PHP} run_tests.php; "
               "r=$?; rm -rf ~/ithub_test; exit $r")


def cmd_check(dest: str) -> None:
    ssh_script(f"cd ~ && find {DESTINOS[dest]['webroot']} -name '*.php' -print0 | xargs -0 -n1 {PHP} -l "
               "| grep -v '^No syntax errors' || echo '[deploy] sintaxis OK'")


def main(argv: list[str]) -> None:
    args = [a for a in argv if not a.startswith("--")]
    flags = {a for a in argv if a.startswith("--")}
    conocidas = {"--produccion", "--pruebas", "--volver"}
    if flags - conocidas:
        ayuda(f"opcion desconocida: {', '.join(sorted(flags - conocidas))}")
    destinos = [d for d in DESTINOS if f"--{d}" in flags]
    if len(destinos) != 1 or not args:
        ayuda("indicar un comando y un destino (--produccion o --pruebas)")
    dest, cmd = destinos[0], args[0]
    if cmd == "app":
        cmd_app(dest, "--volver" in flags)
    elif cmd == "config":
        cmd_config(dest)
    elif cmd == "instalar":
        cmd_instalar(dest)
    elif cmd == "test":
        cmd_test(dest)
    elif cmd == "check":
        cmd_check(dest)
    elif cmd == "run" and len(args) > 1:
        ssh_script(args[1])
    else:
        ayuda(f"comando desconocido: {cmd}")


if __name__ == "__main__":
    main(sys.argv[1:])
