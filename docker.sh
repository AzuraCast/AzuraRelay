#!/usr/bin/env bash
# shellcheck disable=SC2145,SC2178,SC2120,SC2162

# Functions to manage .env files
__dotenv=
__dotenv_file=
__dotenv_cmd=.env

.env() {
  REPLY=()
  [[ $__dotenv_file || ${1-} == -* ]] || .env.--file .env || return
  if declare -F -- ".env.${1-}" >/dev/null; then
    .env."$@"
    return
  fi
  return 64
}

.env.-f() { .env.--file "$@"; }

.env.get() {
  .env::arg "get requires a key" "$@" &&
    [[ "$__dotenv" =~ ^(.*(^|$'\n'))([ ]*)"$1="(.*)$ ]] &&
    REPLY=${BASH_REMATCH[4]%%$'\n'*} && REPLY=${REPLY%"${REPLY##*[![:space:]]}"}
}

.env.parse() {
  local line key
  while IFS= read -r line; do
    line=${line#"${line%%[![:space:]]*}"} # trim leading whitespace
    line=${line%"${line##*[![:space:]]}"} # trim trailing whitespace
    if [[ ! "$line" || "$line" == '#'* ]]; then continue; fi
    if (($#)); then
      for key; do
        if [[ $key == "${line%%=*}" ]]; then
          REPLY+=("$line")
          break
        fi
      done
    else
      REPLY+=("$line")
    fi
  done <<<"$__dotenv"
  ((${#REPLY[@]}))
}

.env.export() { ! .env.parse "$@" || export "${REPLY[@]}"; }

.env.set() {
  .env::file load || return
  local key saved=$__dotenv
  while (($#)); do
    key=${1#+}
    key=${key%%=*}
    if .env.get "$key"; then
      REPLY=()
      if [[ $1 == +* ]]; then
        shift
        continue # skip if already found
      elif [[ $1 == *=* ]]; then
        __dotenv=${BASH_REMATCH[1]}${BASH_REMATCH[3]}$1$'\n'${BASH_REMATCH[4]#*$'\n'}
      else
        __dotenv=${BASH_REMATCH[1]}${BASH_REMATCH[4]#*$'\n'}
        continue # delete all occurrences
      fi
    elif [[ $1 == *=* ]]; then
      __dotenv+="${1#+}"$'\n'
    fi
    shift
  done
  [[ $__dotenv == "$saved" ]] || .env::file save
}

.env.puts() { echo "${1-}" >>"$__dotenv_file" && __dotenv+="$1"$'\n'; }

.env.generate() {
  .env::arg "key required for generate" "$@" || return
  .env.get "$1" && return || REPLY=$("${@:2}") || return
  .env::one "generate: ouptut of '${*:2}' has more than one line" "$REPLY" || return
  .env.puts "$1=$REPLY"
}

.env.--file() {
  .env::arg "filename required for --file" "$@" || return
  __dotenv_file=$1
  .env::file load || return
  (($# < 2)) || .env "${@:2}"
}

.env::arg() { [[ "${2-}" ]] || {
  echo "$__dotenv_cmd: $1" >&2
  return 64
}; }

.env::one() { [[ "$2" != *$'\n'* ]] || .env::arg "$1"; }

.env::file() {
  local REPLY=$__dotenv_file
  case "$1" in
  load)
    __dotenv=
    ! [[ -f "$REPLY" ]] || __dotenv="$(<"$REPLY")"$'\n' || return
    ;;
  save)
    if [[ -L "$REPLY" ]] && declare -F -- realpath.resolved >/dev/null; then
      realpath.resolved "$REPLY"
    fi
    { [[ ! -f "$REPLY" ]] || cp -p "$REPLY" "$REPLY.bak"; } &&
      printf %s "$__dotenv" >"$REPLY.bak" && mv "$REPLY.bak" "$REPLY"
    ;;
  esac
}

# This is a general-purpose function to ask Yes/No questions in Bash, either
# with or without a default answer. It keeps repeating the question until it
# gets a valid answer.
ask() {
  # https://djm.me/ask
  local prompt default reply

  while true; do

    if [[ "${2:-}" = "Y" ]]; then
      prompt="Y/n"
      default=Y
    elif [[ "${2:-}" = "N" ]]; then
      prompt="y/N"
      default=N
    else
      prompt="y/n"
      default=
    fi

    # Ask the question (not using "read -p" as it uses stderr not stdout)
    echo -n "$1 [$prompt] "

    read reply

    # Default?
    if [[ -z "$reply" ]]; then
      reply=${default}
    fi

    # Check if the reply is valid
    case "$reply" in
    Y* | y*) return 0 ;;
    N* | n*) return 1 ;;
    esac

  done
}

# Generate a prompt to set an environment file value.
envfile-set() {
  local VALUE INPUT

  .env --file .env

  .env get "$1"
  VALUE=${REPLY:-$2}

  echo -n "$3 [$VALUE]: "
  read INPUT

  VALUE=${INPUT:-$VALUE}

  .env set "${1}=${VALUE}"
}

# Shortcut to convert semver version (x.yyy.zzz) into a comparable number.
version-number() {
  echo "$@" | awk -F. '{ printf("%03d%03d%03d\n", $1,$2,$3); }'
}

check-install-requirements() {
  local CURRENT_OS CURRENT_ARCH REQUIRED_COMMANDS SCRIPT_DIR

  echo "Checking installation requirements for AzuraRelay..."

  CURRENT_OS=$(uname -s)
  if [[ $CURRENT_OS == "Linux" ]]; then
    echo -en "\e[32m[PASS]\e[0m Operating System: ${CURRENT_OS}\n"
  else
    echo -en "\e[41m[FAIL]\e[0m Operating System: ${CURRENT_OS}\n"

    echo "       You are running an unsupported operating system."
    echo "       Automated AzuraRelay installation is not currently supported on this"
    echo "       operating system."
    exit 1
  fi

  CURRENT_ARCH=$(uname -m)
  if [[ $CURRENT_ARCH == "x86_64" ]]; then
    echo -en "\e[32m[PASS]\e[0m Architecture: ${CURRENT_ARCH}\n"
  elif [[ $CURRENT_ARCH == "aarch64" ]]; then
    echo -en "\e[32m[PASS]\e[0m Architecture: ${CURRENT_ARCH}\n"
  else
    echo -en "\e[41m[FAIL]\e[0m Architecture: ${CURRENT_ARCH}\n"

    echo "       You are running an unsupported processor architecture."
    echo "       Automated AzuraRelay installation is not currently supported on this "
    echo "       operating system."
    exit 1
  fi

  REQUIRED_COMMANDS=(curl awk)
  for COMMAND in "${REQUIRED_COMMANDS[@]}"; do
    if [[ $(command -v "$COMMAND") ]]; then
      echo -en "\e[32m[PASS]\e[0m Command Present: ${COMMAND}\n"
    else
      echo -en "\e[41m[FAIL]\e[0m Command Present: ${COMMAND}\n"

      echo "       ${COMMAND} does not appear to be installed."
      echo "       Install ${COMMAND} using your host's package manager,"
      echo "       then continue installing using this script."
      exit 1
    fi
  done

  if [[ $EUID -ne 0 ]]; then
    if [[ $(command -v sudo) ]]; then
      echo -en "\e[32m[PASS]\e[0m User Permissions\n"
    else
      echo -en "\e[41m[FAIL]\e[0m User Permissions\n"

      echo "       You are not currently the root user, and "
      echo "       'sudo' does not appear to be installed."
      echo "       Install sudo using your host's package manager,"
      echo "       then continue installing using this script."
      exit 1
    fi
  else
    echo -en "\e[32m[PASS]\e[0m User Permissions\n"
  fi

  SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" &>/dev/null && pwd)"
  if [[ $SCRIPT_DIR == "/var/azurarelay" ]]; then
    echo -en "\e[32m[PASS]\e[0m Installation Directory\n"
  else
    echo -en "\e[93m[WARN]\e[0m Installation Directory\n"
    echo "       AzuraRelay is not installed in /var/azurarelay, as is recommended"
    echo "       for most installations. This will not prevent AzuraRelay from"
    echo "       working, but you will need to update any instructions in our"
    echo "       documentation to reflect your current directory:"
    echo "       $SCRIPT_DIR"
  fi

  echo -en "\e[32m[PASS]\e[0m All requirements met!\n"
}

install-docker() {
  set -e

  curl -fsSL get.docker.com -o get-docker.sh
  sh get-docker.sh
  rm get-docker.sh

  if [[ $EUID -ne 0 ]]; then
    sudo usermod -aG docker "$(whoami)"

    echo "You must log out or restart to apply necessary Docker permissions changes."
    echo "Restart, then continue installing using this script."
    exit 1
  fi

  set +e
}

install-docker-compose() {
  set -e
  echo "Installing Docker Compose..."

  curl -fsSL -o docker-compose https://github.com/docker/compose/releases/download/v2.2.2/docker-compose-linux-$(uname -m)

  ARCHITECTURE=amd64
  if [ "$(uname -m)" = "aarch64" ]; then
    ARCHITECTURE=arm64
  fi
  curl -fsSL -o docker-compose-switch https://github.com/docker/compose-switch/releases/download/v1.0.2/docker-compose-linux-${ARCHITECTURE}

  if [[ $EUID -ne 0 ]]; then
    sudo chmod a+x ./docker-compose
    sudo chmod a+x ./docker-compose-switch

    sudo mv ./docker-compose /usr/libexec/docker/cli-plugins/docker-compose
    sudo mv ./docker-compose-switch /usr/local/bin/docker-compose
  else
    chmod a+x ./docker-compose
    chmod a+x ./docker-compose-switch

    mv ./docker-compose /usr/libexec/docker/cli-plugins/docker-compose
    mv ./docker-compose-switch /usr/local/bin/docker-compose
  fi

  echo "Docker Compose updated!"
  set +e
}

#
# Run the initial installer of Docker and AzuraCast.
# Usage: ./docker.sh install
#
install() {
  check-install-requirements

  if [[ $(command -v docker) && $(docker --version) ]]; then
    echo "Docker is already installed! Continuing..."
  else
    if ask "Docker does not appear to be installed. Install Docker now?" Y; then
      install-docker
    fi
  fi

  if [[ $(command -v docker-compose) ]]; then
    echo "Docker Compose is already installed. Continuing..."
  else
    if ask "Docker Compose does not appear to be installed. Install Docker Compose now?" Y; then
      install-docker-compose
    fi
  fi

  if [[ ! -f .env ]]; then
    echo "Writing default .env file..."
    curl -fsSL https://raw.githubusercontent.com/AzuraCast/AzuraRelay/main/.env -o .env
  fi

  if [[ ! -f docker-compose.yml ]]; then
    echo "Retrieving default docker-compose.yml file..."
    curl -fsSL https://raw.githubusercontent.com/AzuraCast/AzuraRelay/main/docker-compose.sample.yml -o docker-compose.yml
  fi

  if [[ ! -f azurarelay.env ]]; then
    touch azurarelay.env
  fi

  if ask "Customize AzuraRelay ports?" N; then
    setup-ports
  fi

  if ask "Set up LetsEncrypt?" N; then
    setup-letsencrypt
  fi

  docker-compose pull
  docker-compose up -d
  sleep 5s

  docker-compose exec --user="app" relay cli app:setup
  docker cp azurarelay_relay:/var/app/www_tmp/azurarelay.env ./azurarelay.env
  docker-compose down -v

  docker-compose up -d
  sleep 5s

  docker-compose exec --user="app" relay cli app:update
  exit
}

#
# Update the Docker images and codebase.
# Usage: ./docker.sh update
#
update() {
  curl -fsSL https://raw.githubusercontent.com/AzuraCast/AzuraRelay/main/docker-compose.sample.yml -o docker-compose.new.yml

  FILES_MATCH="$(
    cmp --silent docker-compose.yml docker-compose.new.yml
    echo $?
  )"
  UPDATE_NEW=0

  if [[ ${FILES_MATCH} -ne 0 ]]; then
    if ask "The docker-compose.yml file has changed since your version. Overwrite? This will overwrite any customizations you made to this file?" Y; then
      UPDATE_NEW=1
    fi
  fi

  if [[ ${UPDATE_NEW} -ne 0 ]]; then
    docker-compose -f docker-compose.new.yml pull
    docker-compose down

    cp docker-compose.yml docker-compose.backup.yml
    mv docker-compose.new.yml docker-compose.yml
  else
    rm docker-compose.new.yml

    docker-compose pull
    docker-compose down
  fi

  local dc_config_test=$(docker-compose config)
  if [ $? -ne 0 ]; then
    if ask "Docker Compose needs to be updated to continue. Update to latest version?" Y; then
      install-docker-compose
    fi
  fi

  docker volume rm azurarelay_tmp_data

  docker-compose up -d
  sleep 5s

  docker-compose exec --user="app" relay cli app:update

  docker rmi $(docker images | grep "none" | awk '/ / { print $3 }') 2>/dev/null

  echo "Update complete!"
  exit
}

#
# Update this Docker utility script.
# Usage: ./docker.sh update-self
#
update-self() {
  curl -fsSL https://raw.githubusercontent.com/AzuraCast/AzuraRelay/main/docker.sh -o docker.sh
  chmod a+x docker.sh

  echo "New Docker utility script downloaded."
  exit
}

#
# Run a CLI command inside the Docker container.
# Usage: ./docker.sh cli [command]
#
cli() {
  docker-compose run --user="app" --rm relay cli $*
  exit
}

#
# Enter the bash terminal of the running web container.
# Usage: ./docker.sh bash
#
bash() {
  docker-compose exec --user="app" relay bash
  exit
}

#
# Stop all Docker containers and remove related volumes.
# Usage: ./docker.sh uninstall
#
uninstall() {
  if ask "This operation is destructive and will wipe your existing Docker containers. Continue? [y/N] " N; then

    docker-compose down -v
    docker-compose rm -f
    docker volume prune -f

    echo "All AzuraRelay Docker containers and volumes were removed."
    echo "To remove *all* Docker containers and volumes, run:"
    echo "  docker stop \$(docker ps -a -q)"
    echo "  docker rm \$(docker ps -a -q)"
    echo "  docker volume prune -f"
    echo ""
  fi

  exit
}

#
# Configure the ports used by AzuraRelay.
#
setup-ports() {
  envfile-set "AZURARELAY_HTTP_PORT" "80" "Port to use for HTTP connections"
  envfile-set "AZURARELAY_HTTPS_PORT" "443" "Port to use for HTTPS connections"
}

#
# Configure the settings used by LetsEncrypt.
#
setup-letsencrypt() {
  envfile-set "LETSENCRYPT_HOST" "" "Domain name (example.com) or names (example.com,foo.bar) to use with LetsEncrypt"
  envfile-set "LETSENCRYPT_EMAIL" "" "Optional e-mail address for expiration updates"
}

# Ensure we're in the same directory as this script.
cd "${BASH_SOURCE%/*}/" || exit

"$@"

