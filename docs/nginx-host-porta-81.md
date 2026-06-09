# Nginx no host (porta 81) com alias /var/www/ieducar

## Problema: `fastcgi_pass php-fpm`

No host **sem Docker** não existe o hostname `php-fpm`. O PHP-FPM roda no sistema e usa um **socket**. Se a config usar `fastcgi_pass php-fpm;`, o Nginx não consegue falar com o PHP e aparece "File not found" / "Primary script unknown".

## Correção no `/etc/nginx/conf.d/ieducar.conf`

Troque a linha:

```nginx
fastcgi_pass php-fpm;
```

por (PHP 8.4):

```nginx
fastcgi_pass unix:/run/php/php8.4-fpm.sock;
```

Se a sua versão for outra (ex.: 8.2 ou 8.3), use o socket correspondente:

```bash
ls /run/php/
```

Ex.: `php8.3-fpm.sock` → `fastcgi_pass unix:/run/php/php8.3-fpm.sock;`

Depois:

```bash
sudo nginx -t && sudo systemctl reload nginx
```

---

## Alias /var/www/ieducar → home

Se `/var/www/ieducar` for um **symlink** para `/home/williams/projetos/edu/i-educar`, o usuário do Nginx (`www-data`) precisa conseguir **atravessar** até a pasta do projeto:

- `/var`, `/var/www` → normalmente já são `755`
- **Cada diretório no caminho até o projeto** precisa ter permissão de execução (`x`) para "outros" (ou para o grupo de `www-data`), senão dá **Permission denied**.

Para liberar só a árvore do projeto (sem abrir todo o home):

```bash
# Tornar o caminho até o projeto atravessável por www-data (uma vez)
chmod o+x /home/williams /home/williams/projetos /home/williams/projetos/edu

# Ajustar permissões dos arquivos do projeto para leitura por outros
sudo /home/williams/projetos/edu/i-educar/script/fix-web-permissions-host.sh /home/williams/projetos/edu/i-educar
```

Recarregar o Nginx após alterar permissões:

```bash
sudo systemctl reload nginx
```
