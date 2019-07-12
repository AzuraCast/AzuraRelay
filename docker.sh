#!/usr/bin/env bash

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
            Y*|y*) return 0 ;;
            N*|n*) return 1 ;;
        esac

    done
}

#
# Run the initial installer of Docker and AzuraCast.
# Usage: ./docker.sh install
#
install() {
    if [[ $(which docker) && $(docker --version) ]]; then
        echo "Docker is already installed! Continuing..."
    else
        if ask "Docker does not appear to be installed. Install Docker now?" Y; then
            curl -fsSL get.docker.com -o get-docker.sh
            sh get-docker.sh
            rm get-docker.sh

            if [[ $EUID -ne 0 ]]; then
                sudo usermod -aG docker `whoami`

                echo "You must log out or restart to apply necessary Docker permissions changes."
                echo "Restart, then continue installing using this script."
                exit
            fi
        fi
    fi

    if [[ $(which docker-compose) && $(docker-compose --version) ]]; then
        echo "Docker Compose is already installed! Continuing..."
    else
        if ask "Docker Compose does not appear to be installed. Install Docker Compose now?" Y; then
            if [[ ! $(which git) ]]; then
                echo "Git does not appear to be installed."
                echo "Install git using your host's package manager,"
                echo "then continue installing using this script."
                exit 1
            fi

            if [[ ! $(which curl) ]]; then
                echo "cURL does not appear to be installed."
                echo "Install curl using your host's package manager,"
                echo "then continue installing using this script."
                exit 1
            fi

            COMPOSE_VERSION=`git ls-remote https://github.com/docker/compose | grep refs/tags | grep -oP "[0-9]+\.[0-9][0-9]+\.[0-9]+$" | tail -n 1`

            if [[ $EUID -ne 0 ]]; then
                if [[ ! $(which sudo) ]]; then
                    echo "Sudo does not appear to be installed."
                    echo "Install sudo using your host's package manager,"
                    echo "then continue installing using this script."
                    exit 1
                fi

                sudo sh -c "curl -L https://github.com/docker/compose/releases/download/${COMPOSE_VERSION}/docker-compose-`uname -s`-`uname -m` > /usr/local/bin/docker-compose"
                sudo chmod +x /usr/local/bin/docker-compose
                sudo sh -c "curl -L https://raw.githubusercontent.com/docker/compose/${COMPOSE_VERSION}/contrib/completion/bash/docker-compose > /etc/bash_completion.d/docker-compose"
            else
                curl -L https://github.com/docker/compose/releases/download/${COMPOSE_VERSION}/docker-compose-`uname -s`-`uname -m` > /usr/local/bin/docker-compose
                chmod +x /usr/local/bin/docker-compose
                curl -L https://raw.githubusercontent.com/docker/compose/${COMPOSE_VERSION}/contrib/completion/bash/docker-compose > /etc/bash_completion.d/docker-compose
            fi
        fi
    fi

    if [[ ! -f .env ]]; then
        echo "Writing default .env file..."
        curl -L https://raw.githubusercontent.com/AzuraCast/AzuraRelay/master/.env > .env
    fi

    if [[ ! -f docker-compose.yml ]]; then
        echo "Retrieving default docker-compose.yml file..."
        curl -L https://raw.githubusercontent.com/AzuraCast/AzuraCast/master/docker-compose.sample.yml > docker-compose.yml
    fi

    docker-compose pull

    if [[ ! -f azurarelay.env ]]; then
        docker-compose up -d
        docker-compose run --rm --user="azurarelay" relay cli app:setup
        docker cp azurarelay_relay_1:/var/azurarelay/www_tmp/azurarelay.env ./azurarelay.env
        docker-compose down -v
    fi

    docker-compose up -d
    docker-compose exec --user="azurarelay" relay cli app:update
    exit
}

#
# Update the Docker images and codebase.
# Usage: ./docker.sh update
#
update() {
    docker-compose down
    docker-compose rm -f

    if ask "Update docker-compose.yml file? This will overwrite any customizations you made to this file?" Y; then

        cp docker-compose.yml docker-compose.backup.yml
        echo "Your existing docker-compose.yml file has been backed up to docker-compose.backup.yml."

        curl -L https://raw.githubusercontent.com/AzuraCast/AzuraRelay/master/docker-compose.sample.yml > docker-compose.yml
        echo "New docker-compose.yml file loaded."

    fi

    docker volume rm azurarelay_www_data
    docker volume rm azurarelay_tmp_data
    docker-compose pull
    docker-compose up -d

    docker rmi $(docker images | grep "none" | awk '/ / { print $3 }') 2> /dev/null

    echo "Update complete!"
    exit
}

#
# Update this Docker utility script.
# Usage: ./docker.sh update-self
#
update-self() {
    curl -L https://raw.githubusercontent.com/AzuraCast/AzuraRelay/master/docker.sh > docker.sh
    chmod a+x docker.sh

    echo "New Docker utility script downloaded."
    exit
}

#
# Run a CLI command inside the Docker container.
# Usage: ./docker.sh cli [command]
#
cli() {
    docker-compose run --user="azurarelay" --rm relay cli $*
    exit
}

#
# Enter the bash terminal of the running web container.
# Usage: ./docker.sh bash
#
bash() {
    docker-compose exec --user="azurarelay" relay bash
    exit
}

#
# DEVELOPER TOOL:
# Run the full test suite.
#
dev-tests() {
    docker-compose exec --user="azurarelay" relay composer phplint -- $*
    docker-compose exec --user="azurarelay" relay composer phpstan -- $*
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

$*
