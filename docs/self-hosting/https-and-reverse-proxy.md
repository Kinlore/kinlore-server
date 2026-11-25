# HTTPS and reverse proxy

Phones need HTTPS for a server on the internet. HTTPS encrypts the connection with a certificate. This page shows the ways to give your Kinlore server HTTPS.

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

Some internet providers give no public IPv4 address of your own (this is called CGNAT). Then port forwarding cannot work over IPv4. Ask your provider for a public address, or use IPv6.

## Choose a way

| Way | Use it when |
|---|---|
| [Built-in HTTPS (Docker)](#built-in-https-docker) | The machine runs only Kinlore on ports 80 and 443. This is the simplest way. |
| [Your own reverse proxy (Docker)](#your-own-reverse-proxy-docker) | The machine already runs a proxy for other sites. |
| [Host install](#host-install) | You installed without Docker. Caddy or nginx of the guide already serves HTTPS. |

## Built-in HTTPS (Docker)

The web server in the API container is Caddy. Caddy gets and renews a certificate from Let's Encrypt by itself.

1. Set these lines in `.env` next to `docker-compose.yml`:
   ```ini
   KINLORE_DOMAIN=photos.example.com
   APP_URL=https://photos.example.com
   ```
2. Keep `APP_PORT` at `80` and `APP_SSL_PORT` at `443`.
3. Start the stack again:
   ```sh
   docker compose up -d
   ```

The certificate is kept in the volume `caddy_data`. For a local test, use `KINLORE_DOMAIN=localhost`. Caddy then makes its own certificate, which browsers and phones do not trust.

## Your own reverse proxy (Docker)

Leave `KINLORE_DOMAIN` empty. The API container then serves plain HTTP, and your proxy adds HTTPS.

1. Move the API off ports 80 and 443, and make it reachable only from this machine. Set in `.env`:
   ```ini
   APP_URL=https://photos.example.com
   APP_PORT=8080
   APP_SSL_PORT=8443          # not used in this mode; moved so 443 stays free for your proxy
   APP_BIND_ADDRESS=127.0.0.1
   ```
2. Start the stack again: `docker compose up -d`.
3. Set up your proxy with one of the examples below.
4. Set `TRUSTED_PROXIES` to the address of your proxy. See [Trusted proxies](#trusted-proxies).

### What your proxy must do

- Forward the headers `Host`, `X-Forwarded-Proto`, `X-Forwarded-Host` and `X-Forwarded-For`.
- Allow large request bodies. One video can be up to 500 MB.
- Allow long requests. An upload of a large video or the download of an export can take hours on a slow connection. Set no limit for the whole request. A limit on the time between two reads, such as `proxy_read_timeout` in nginx, can be 30 minutes.

### Caddy on the host

```
photos.example.com {
    reverse_proxy 127.0.0.1:8080
}
```

Caddy sends the needed headers by itself and has no body size limit.

### nginx on the host

```nginx
server {
    listen 443 ssl http2;
    server_name photos.example.com;
    # ssl_certificate ... ; ssl_certificate_key ... ;

    client_max_body_size 0;
    proxy_read_timeout 1800s;
    proxy_send_timeout 1800s;
    proxy_request_buffering off;

    location / {
        proxy_pass http://127.0.0.1:8080;   # APP_PORT of the API
        proxy_set_header Host $host;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header X-Forwarded-Host $host;
        proxy_set_header X-Forwarded-Port $server_port;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    }
}
```

### Traefik

Add these labels to the `api` service, for example in `docker-compose.override.yml`:

```yaml
services:
  api:
    labels:
      - traefik.enable=true
      - traefik.http.routers.kinlore.rule=Host(`photos.example.com`)
      - traefik.http.routers.kinlore.entrypoints=websecure
      - traefik.http.routers.kinlore.tls.certresolver=letsencrypt
      - traefik.http.services.kinlore.loadbalancer.server.port=80
```

Traefik must share a Docker network with the API container. Raise its `respondingTimeouts` for long uploads.

## Trusted proxies

The server trusts the `X-Forwarded-*` headers only from the addresses in `TRUSTED_PROXIES`. With these headers, the server knows that the visitor used `https`. Without them, video and export links start with `http://`, and iPhones refuse them.

The default trusts all private networks. So a device on your home network could also send these headers. Set `TRUSTED_PROXIES` to the address of your proxy whenever you know it:

- **A proxy on the Docker host:** the address of the Docker gateway, for example `172.18.0.1`. Find it with `docker network inspect kinlore-server_kinlore-network`, in the field `Gateway`. The first part of the name is the folder that holds `docker-compose.yml`.
- **A proxy on another machine:** its IP address.

On some hosts, the API container sees every client as the Docker gateway. With the default value, such a client can then fake its own address with an `X-Forwarded-For` header. This is one more reason to set `TRUSTED_PROXIES`.

## Host install

The guide [Install without Docker](/self-hosting/bare-metal) sets up HTTPS in step 6: Caddy gets a certificate by itself, and nginx gets one with `certbot`.

### Host install behind another proxy

If the machine already runs a reverse proxy for other sites, you have two choices:

- Add Kinlore to that proxy, and let it pass the requests to the web server of step 6 on another port.
- Serve Kinlore from that proxy directly, with the settings of the files in `deploy/bare-metal/`.

The proxy must forward the headers of [What your proxy must do](#what-your-proxy-must-do). `TRUSTED_PROXIES=127.0.0.1` in `kinlore-api/.env` trusts a proxy on the same machine. For a proxy on another machine, put its address there. Run `sudo -u www-data php artisan config:cache` after the change.

## Check that it worked

```sh
curl -fsS https://photos.example.com/api/health
```

The answer starts with `{"status":"ok"`. Then play a video in the app. A video plays only when its links use `https`.

## When it does not work

- **Caddy gets no certificate.** The host name does not point at the server, or port 80 or 443 is closed. Read the log: `docker compose logs api`, or `journalctl -u caddy -n 50` without Docker.
- **Videos do not play on an iPhone, or links start with `http://`.** `APP_URL` must be the `https://` address. Your proxy must send `X-Forwarded-Proto`, and its address must be in `TRUSTED_PROXIES`.
- **Large uploads fail with "413".** Your proxy limits the body size. Set `client_max_body_size 0;` in nginx.
- **Long uploads stop after some time.** Your proxy has a timeout. Raise it.
