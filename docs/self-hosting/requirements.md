# Requirements

This page lists what the Kinlore server needs. Check it before you install.

## Two ways to install

Both ways use the same code and the same variables.

| | [Docker Compose](/self-hosting/docker) (recommended) | [Host install without Docker](/self-hosting/bare-metal) |
|---|---|---|
| What you install | Docker with Docker Compose | PHP, Composer, FFmpeg and a web server |
| System | any Linux machine that runs Docker | Debian 13, or Ubuntu 24.04 or later |
| Database | PostgreSQL 18, in a container | SQLite (a single file) or PostgreSQL |
| Media storage | SeaweedFS, in containers | a folder on the disk, or S3 storage |
| Cache and rate limits | Redis, in a container | the database |
| Web server and HTTPS | Caddy, in the API container | Caddy or nginx on the host |

Choose Docker Compose when you are not sure. One command starts every part.

## Hardware

| Part | What to plan |
|---|---|
| Memory | 2 GB or more. With 1 or 2 GB, see [Large photo uploads fail on a small server](/self-hosting/troubleshooting#large-photo-uploads-fail-on-a-small-server). |
| Disk | The space of all your photos and videos, plus about the same again for backups. The server keeps every original file. Each video also gets a stream copy in three sizes. |
| Processor | Video conversion uses the processor. On a slow machine, a video takes longer to be ready. |

## Docker and Docker Compose

| Part | Version | Check it with |
|---|---|---|
| Docker Engine | a current release | `docker version` |
| Docker Compose | 2.24.4 or newer | `docker compose version` |

`deploy/docker/reverse-proxy.yml` uses the `!override` tag of Docker Compose. Older versions stop with an error about this tag.

::: tip Install Docker
On Debian and Ubuntu, the official script installs Docker with Docker Compose:

```sh [Install Docker]
curl -fsSL https://get.docker.com | sudo sh
```

To run `docker` without `sudo`, add your user to the `docker` group with `sudo usermod -aG docker $USER`, then sign in again.
:::

## Network

| Use | What you need |
|---|---|
| On the internet | A host name that points at the machine, for example `photos.example.com`, and HTTPS. iPhones need HTTPS for an address on the internet. Kinlore can get the certificate by itself. |
| At home only | The address of the machine on your network, for example `http://192.168.1.20`. Plain HTTP works for local addresses. |
| Ports | 80 and 443 for built-in HTTPS. Behind your own reverse proxy, the port of that proxy. |

For a server at home that must be reachable from the internet, see [Make a server at home reachable](/self-hosting/https-and-reverse-proxy#make-a-server-at-home-reachable).

## Software versions

| Part | Version | Notes |
|---|---|---|
| PHP | 8.5 | in the Docker image; install it yourself on a host install |
| Laravel | 13 | comes with the code |
| PostgreSQL | 18 in Docker; 15 or later on a host install | optional on a host install |
| SeaweedFS | 4.55 | Docker only; pinned, because it holds all media |
| Redis | 8.10 | Docker only; cache and rate limits |
| Caddy | 2 | in the Docker image |

## The app

The family needs the Kinlore app on iPhone or Android. Use app 1.1 or newer. See [Install the app](/app/install).

## Next steps

- [Install with Docker Compose](/self-hosting/docker)
- [Host install without Docker](/self-hosting/bare-metal)
- [Quick start](/guide/quick-start)
