![](https://github.com/AzuraCast/AzuraRelay/raw/main/azurarelay.png)![](https://static.scarf.sh/a.png?x-pxid=7ff80422-80b2-4155-83f1-0d45a3b792b7)

# AzuraRelay: AzuraCast's Lightweight, Automated Relay Companion

A "relay in a box" containing a lightweight web application and Icecast servers that can connect to and relay an AzuraRelay parent instance.

## What it Does

After you finish the initial setup, AzuraRelay will:
 - Connect to a parent AzuraCast instance and list all relayable streams,
 - Automatically relay those streams via Icecast using the same ports and URLs as the parent installation, and
 - Report itself back to AzuraCast so listeners can select it as a stream and you can view listener data from it.

## Parent Installation Requirements

Before installing AzuraRelay, make sure your "parent" AzuraCast installation:

- Is updated to the latest version, and
- Has the direct radio ports (i.e. 8000, 8010) exposed; if you use a service like Cloudflare to protect your server, enter the server's direct IP address when prompted for the parent URL in the AzuraRelay setup.

## Installing

AzuraRelay is powered by Docker and uses pre-built images that contain every component of the software. Don't worry if you aren't very familiar with Docker; our easy installer tools will handle installing Docker and Docker Compose for you, and updates are very simple.

### System Requirements

- A 64-bit x86 (x86_64) CPU or ARM64 CPU (like the Raspberry Pi 3/4)
- 512MB or greater of RAM
- 10GB or greater of hard drive space

For Linux hosts, the `sudo`, `curl` and `git` packages should be installed before installing. Most Linux distributions include these packages already.

### Installing

Connect to the server or computer you want to install AzuraRelay on via an SSH terminal. You should be an administrator user with either root access or the ability to use the `sudo` command.

Pick a base directory on your host computer that AzuraRelay can use. If you're on Linux, you can follow the steps below to use the recommended directory:

```bash
mkdir -p /var/azurarelay
cd /var/azurarelay
```

Use these commands to download our Docker Utility Script, set it as executable and then run the Docker installation process:

```bash
curl -L https://raw.githubusercontent.com/AzuraCast/AzuraRelay/main/docker.sh > docker.sh
chmod a+x docker.sh
./docker.sh install
```

On-screen prompts will show you how the installation is progressing.

### Updating

Using the included Docker utility script, updating is as simple as running:

```bash
./docker.sh update-self
./docker.sh update
```
