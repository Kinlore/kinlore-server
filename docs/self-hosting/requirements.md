# Requirements

This page lists what the Kinlore server needs. Read it before you choose an install method.

## Two ways to install

Both ways use the same code and the same settings.

| | [Docker](/self-hosting/docker) (recommended) | [Without Docker](/self-hosting/bare-metal) |
|---|---|---|
| What you install | Docker with Docker Compose | PHP, Composer, FFmpeg and a web server |
| Database | PostgreSQL 18, in a container | SQLite (a single file) or PostgreSQL |
| Media storage | SeaweedFS, in containers | a folder on the local disk, or S3 storage |
| Cache and rate limits | Redis, in a container | the database |
| Web server and HTTPS | Caddy, in the API container | Caddy or nginx on the host |
| System | any Linux machine that runs Docker | Debian 13, or Ubuntu 24.04 or later |

Choose Docker when you are not sure. One command starts every part.

## Hardware

- **Memory.** Plan 2 GB or more. On a machine with 1 or 2 GB, follow the steps for small servers in [Troubleshooting](/self-hosting/troubleshooting#large-photo-uploads-fail-on-a-small-server).
- **Disk.** The server keeps every original file. Plan the space of all your photos and videos, plus about the same again for backups. Videos also get a stream copy in three sizes.
- **Processor.** Video conversion uses the processor. On a slow machine, a video takes longer to be ready.

## Network

- **For use on the internet:** a host name that points at the machine (for example `photos.example.com`), and HTTPS. iPhones need HTTPS for an address on the internet. Kinlore can get the certificate by itself. See [HTTPS and reverse proxy](/self-hosting/https-and-reverse-proxy).
- **For use at home only:** the address of the machine on your network is enough, for example `http://192.168.1.20`. Plain HTTP works for local addresses.
- **Ports.** 80 and 443 for built-in HTTPS. Another port is fine behind your own reverse proxy.

## Software versions

| Part | Version | Notes |
|---|---|---|
| PHP | 8.5 | in the Docker image; install it yourself without Docker |
| Laravel | 13 | comes with the code |
| PostgreSQL | 18 in Docker; 15 or later without Docker | optional without Docker |
| SeaweedFS | 4.55 | Docker only; pinned, because it holds all media |
| Redis | 8.10 | Docker only; cache and rate limits |
| Caddy | 2 | in the Docker image |

## The app

The family needs the Kinlore app on iPhone or Android. Use server 1.1 with app 1.1 or newer. See [Install the app](/app/install).

## Next steps

- [Install with Docker](/self-hosting/docker)
- [Install without Docker](/self-hosting/bare-metal)
