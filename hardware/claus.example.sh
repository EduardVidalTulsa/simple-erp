#!/bin/sh
# Credencials per als scripts de bàscula física (pesa, pesa1r, pesa1r.esclau).
# Copia'l com a "claus.sh" al mateix directori que aquests scripts (mai el pugis a git),
# i omple-hi les dades de la teva instal·lació.

mysqluser=erp            # usuari de MariaDB (el mateix que a keys.php)
mypass='canvia-aquesta-contrasenya'
master=localhost         # servidor MariaDB on hi ha la taula `ultimpes`
idbascula=1              # ID d'aquesta bàscula a la taula `ultimpes`
