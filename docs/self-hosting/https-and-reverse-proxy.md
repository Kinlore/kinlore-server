# HTTPS and reverse proxy

Phones need HTTPS to use a server on the internet. HTTPS encrypts the connection with a certificate. This page shows two ways to give your Kinlore server HTTPS.

A reverse proxy is a web server in front of another web server. It receives the requests from the internet, adds HTTPS, and passes the requests on.

## What you get

- The server at an `https://` address, with a trusted certificate that renews by itself.
- Correct `https://` links for videos, exports and invitations.

## What you need

- A host name that points at the server, for example `photos.example.com`.
- Ports 80 and 443 open to the internet, on the server or on your proxy.

The next section shows how to get both for a server at home.

## Make a server at home reachable

A server at home sits behind your internet router. The router has one public address for all devices in your home.

1. **Get a host name.** Buy a domain from a domain registrar, or use a free dynamic DNS service. A dynamic DNS service gives you a name such as `photos.example.com` and follows your public address when it changes.
2. **Point the name at your home.** At your registrar or DNS service, make an `A` record for the host name. Its value is your public IPv4 address. If your connection has IPv6, also make an `AAAA` record with the IPv6 address of the server.
3. **Give the server a fixed address in your home network.** In the router settings, reserve the address of the server (often called "DHCP reservation"), for example `192.168.1.20`.
4. **Forward the ports.** In the router settings, forward TCP ports 80 and 443 to that address (often called "port forwarding" or "virtual server"). For IPv6, allow ports 80 and 443 to the server in the router's firewall instead.

Check it: on a phone that uses mobile data, not your Wi-Fi, open `http://photos.example.com`. A page from the server must load.

::: warning No public IPv4 address
Some internet providers give no public IPv4 address of your own. This is called CGNAT. Then port forwarding cannot work over IPv4. Ask your provider for a public address, or use IPv6.
:::

## Choose a way

| Way | Use it when |
|---|---|
| [Built-in HTTPS](#built-in-https) | The machine runs only Kinlore on ports 80 and 443. This is the simplest way. |
| [Your own reverse proxy](#behind-a-reverse-proxy-on-this-machine) | The machine already runs a web server or a proxy for other sites. |

Without Docker, the guide [Install without Docker](./bare-metal.md) sets up HTTPS for you. See [Install without Docker](#install-without-docker).

## Built-in HTTPS

The API container has its own web server, Caddy. Caddy gets a certificate from Let's Encrypt and renews it by itself.

1. Set these lines in `.env` next to `docker-compose.yml`:
   ```ini [.env]
   KINLORE_DOMAIN=photos.example.com
   APP_URL=https://photos.example.com
   APP_PORT=80
   APP_SSL_PORT=443
   ```
   The certificate check of Let's Encrypt needs ports 80 and 443. Without these two lines, the server uses ports 28461 and 28462.
2. Start the server again:
   ```sh [Start the server]
   docker compose up -d
   ```

The server now uses ports 80 and 443. Caddy keeps the certificate in its data folder, so it survives a restart.

::: tip Test on your own computer
Use `KINLORE_DOMAIN=localhost`. Caddy then makes its own certificate. Browsers and phones do not trust it, but you can test the setup.
:::

## Behind a reverse proxy on this machine

Use this way when another web server on this machine already uses ports 80 and 443. The file `deploy/docker/reverse-proxy.yml` changes three things:

- The API listens only on `127.0.0.1:28461`. Only programs on this machine can reach it.
- The API serves plain HTTP. Your proxy does HTTPS.
- The Docker network of Kinlore gets a fixed gateway, `172.31.250.1`. The API trusts the proxy headers from this address. So you do not have to set `TRUSTED_PROXIES`.

::: warning Docker Compose 2.24.4 or later
The file uses the `!override` tag. Older versions of Docker Compose stop with an error about this tag. Check your version with `docker compose version`.
:::

### Turn it on

1. Edit `.env` next to `docker-compose.yml`. Add the `COMPOSE_FILE` line, and remove the lines that this file replaces:
   ```diff [.env]
   +COMPOSE_FILE=docker-compose.yml:deploy/docker/reverse-proxy.yml
    APP_URL=https://photos.example.com
   -KINLORE_DOMAIN=photos.example.com
   -APP_PORT=80
   -APP_SSL_PORT=443
   -TRUSTED_PROXIES=...
   ```
   `APP_URL` must be the `https://` address. Docker Compose reads `COMPOSE_FILE` from `.env` by itself.
2. Stop the server, then start it again:
   ```sh [Restart the server]
   docker compose down
   docker compose up -d
   ```
   ::: warning Use `down`, then `up`
   The network changes, so every container must join it again. `docker compose up -d` alone can leave the storage containers unable to find each other.
   :::
3. Check that the API answers on this machine:
   ```sh [Check the API]
   curl -fsS http://127.0.0.1:28461/api/health
   ```
   The answer starts with `{"status":"ok"`.
4. Set up your proxy with one of the examples below.

::: tip Data in a folder of your choice too?
Add the second file with a colon: `COMPOSE_FILE=docker-compose.yml:deploy/docker/reverse-proxy.yml:deploy/docker/bind-mounts.yml`. See [Store the data in a folder of your choice](./data-locations.md).
:::

### What your proxy must do

- **Forward the headers** `Host`, `X-Forwarded-Proto`, `X-Forwarded-Host` and `X-Forwarded-For`. With them, the server knows that the visitor used `https`.
- **Allow large requests.** One photo or video can be up to 500 MB.
- **Allow long requests.** The upload of a large video or the download of an export can take hours on a slow line. Set no limit for the whole request. A limit on the time between two reads can be 30 minutes.

Kinlore uses no websockets, so your proxy needs no settings for them.

### Proxy examples

Replace `photos.example.com` with your host name. If you changed `APP_PORT`, replace `28461` too.

::: code-group

```txt [Caddy]
# /etc/caddy/Caddyfile
# Caddy gets the certificate, sends the forwarded headers,
# and has no size limit or timeout by default.
photos.example.com {
	reverse_proxy 127.0.0.1:28461
}
```

```nginx [nginx]
# /etc/nginx/sites-available/kinlore
# Get the certificate first: sudo certbot certonly --nginx -d photos.example.com
server {
    listen 80;
    server_name photos.example.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name photos.example.com;
    ssl_certificate     /etc/letsencrypt/live/photos.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/photos.example.com/privkey.pem;

    client_max_body_size 600m;    # one file can be up to 500 MB
    proxy_request_buffering off;  # pass uploads on at once
    proxy_read_timeout 1800s;     # slow uploads and large exports
    proxy_send_timeout 1800s;

    location / {
        proxy_pass http://127.0.0.1:28461;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header X-Forwarded-Host $host;
        proxy_set_header X-Forwarded-Port $server_port;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    }
}
```

```yaml [Traefik: traefik.yml]
# Static settings of Traefik. Only the parts that Kinlore needs.
entryPoints:
  web:
    address: ":80"
    http:
      redirections:
        entryPoint:
          to: websecure
          scheme: https
  websecure:
    address: ":443"
    transport:
      respondingTimeouts:
        readTimeout: 0   # no limit: a large upload on a slow line can take hours
certificatesResolvers:
  letsencrypt:
    acme:
      email: admin@example.com
      storage: /etc/traefik/acme.json
      httpChallenge:
        entryPoint: web
providers:
  file:
    filename: /etc/traefik/kinlore.yml
```

```yaml [Traefik: kinlore.yml]
# Route for Kinlore. Traefik sends the forwarded headers by itself.
http:
  routers:
    kinlore:
      rule: Host(`photos.example.com`)
      entryPoints: [websecure]
      service: kinlore
      tls:
        certResolver: letsencrypt
  services:
    kinlore:
      loadBalancer:
        servers:
          - url: http://127.0.0.1:28461
```

:::

After a change to nginx, run `sudo nginx -t && sudo systemctl reload nginx`. After a change to Caddy, run `sudo systemctl reload caddy`.

::: tip Traefik in a Docker container
In a container, `127.0.0.1` is the container itself, not this machine. Connect the Traefik container to the network of Kinlore with a fixed address, for example `172.31.250.250`. Use `http://kinlore-api:80` as the server address. Then set `TRUSTED_PROXIES=172.31.250.250` in `.env`. Find the name of the network in [Trusted proxies](#trusted-proxies).
:::

### A proxy on another machine

The proxy can also run on another machine in your network, for example a router or a small server.

1. Make the API reachable from your network. Set the address of the Kinlore server in `.env`, together with the file of this section:
   ```ini [.env]
   COMPOSE_FILE=docker-compose.yml:deploy/docker/reverse-proxy.yml
   APP_URL=https://photos.example.com
   APP_BIND_ADDRESS=192.168.1.20      # this server
   TRUSTED_PROXIES=192.168.1.10       # the machine with the proxy
   ```
2. Restart the server with `docker compose down` and `docker compose up -d`.
3. In the proxy examples above, replace `127.0.0.1:28461` with `192.168.1.20:28461`.

The API then sees the real address of the proxy. So `TRUSTED_PROXIES` must hold that address, not the gateway.

::: danger The host firewall does not protect Docker ports
Docker writes its own firewall rules. A rule in `ufw` or `firewalld` does not block a port that Docker publishes. Use `APP_BIND_ADDRESS` to choose who can reach the API. Never put a public address there.
:::

## Trusted proxies

The server trusts the `X-Forwarded-*` headers only from the addresses in `TRUSTED_PROXIES`. With these headers, the server knows that the visitor used `https`. Without them, video and export links start with `http://`, and iPhones refuse them.

A proxy on the same machine reaches the API container through the network gateway of Docker. So the API sees the address of the gateway, not `127.0.0.1`.

- **With `deploy/docker/reverse-proxy.yml`:** the gateway is always `172.31.250.1`. The file trusts it by itself. Remove any other `TRUSTED_PROXIES` line from `.env`.
- **Without the file:** Docker picks the gateway at the first start. Find the project name, then the gateway:
  ```sh [Find the gateway]
  docker compose ls
  docker network inspect kinlore-server_kinlore-network --format '{{(index .IPAM.Config 0).Gateway}}'
  ```
  The first command lists the project names in the column `NAME`. Replace `kinlore-server` in the second command with your project name. Put the printed address in `.env`, for example `TRUSTED_PROXIES=172.18.0.1`. Then run `docker compose up -d`.
- **A proxy on another machine:** its IP address.

::: warning The default trusts every private network
Without `TRUSTED_PROXIES`, the server trusts all private addresses. Then a device in your home network can also send these headers. Set `TRUSTED_PROXIES` to the address of your proxy whenever you can.
:::

::: details The address 172.31.250.1 is already in use
Another Docker network or your home network may use the network `172.31.250.0/24`. Then `docker compose up` stops with an error such as `Pool overlaps with other one on this address space`. Pick another free network in `.env`:

```ini [.env]
KINLORE_SUBNET=172.31.251.0/24
KINLORE_GATEWAY=172.31.251.1
```

The server then trusts the new gateway by itself.
:::

## Install without Docker

The guide [Install without Docker](./bare-metal.md) sets up HTTPS in step 6. Caddy gets a certificate by itself, and nginx gets one with `certbot`.

If the machine already runs a reverse proxy for other sites, you have two choices:

- Add Kinlore to that proxy, and let it pass the requests to the web server of step 6 on another port.
- Serve Kinlore from that proxy directly, with the settings of the files in `deploy/bare-metal/`.

The proxy must do the things in [What your proxy must do](#what-your-proxy-must-do). `TRUSTED_PROXIES=127.0.0.1` in `kinlore-api/.env` trusts a proxy on the same machine. For a proxy on another machine, put its address there. Run `sudo -u www-data php artisan config:cache` after the change.

## Check that it worked

Run these commands on any computer:

```sh [Check HTTPS]
curl -fsS https://photos.example.com/api/health
curl -sSI https://photos.example.com/ https://photos.example.com/setup | grep -i '^location'
```

- The first command prints a line that starts with `{"status":"ok"`.
- The second command prints one `location:` line. It must start with `https://photos.example.com`. If it starts with `http://`, see the problems below.

Then play a video in the app. A video plays only when its links use `https`.

## When it does not work

- **Caddy gets no certificate.** The host name does not point at the server, or port 80 or 443 is closed. Read the log: `docker compose logs api`. Without Docker: `journalctl -u caddy -n 50`.
- **`docker compose up` stops with an error about `!override`.** Docker Compose is older than 2.24.4. Update Docker.
- **`docker compose up` stops with `address already in use` on port 80.** `.env` still has `APP_PORT=80`. Remove the line, so the API uses port 28461.
- **The proxy shows "502 Bad Gateway".** The API is not running, or the proxy uses the wrong port. Run `curl -fsS http://127.0.0.1:28461/api/health` on the server.
- **Links start with `http://`, or videos do not play on an iPhone.** `APP_URL` must be the `https://` address. Your proxy must send `X-Forwarded-Proto`. Its address must be in `TRUSTED_PROXIES`. With `deploy/docker/reverse-proxy.yml`, remove any `TRUSTED_PROXIES` line from `.env`.
- **The API does not start, and `docker compose logs seaweedfs-filer` shows `no such host`.** The network changed while the containers ran. Run `docker compose down`, then `docker compose up -d`.
- **Large uploads fail with "413".** Your proxy limits the body size. In nginx, set `client_max_body_size 600m;`.
- **Long uploads stop after some time.** Your proxy has a timeout. Raise it. In Traefik, set `readTimeout: 0`.

More problems and fixes are in [Troubleshooting](./troubleshooting.md).

## Next steps

- [Create the first admin](./first-admin.md)
- [Store the data in a folder of your choice](./data-locations.md)
- [Backup and restore](./backup-and-restore.md)
