FROM debian:10
ARG UID
ARG GID
ARG USERNAME

RUN apt update && apt upgrade -y && apt install -y sudo ansible vim git git-flow fish iputils-ping php-cli sshpass iproute2 python3-boto3 python3-botocore python3-dateutil python3-requests php-xml php-mbstring php-geoip php-soap php-curl unzip php-yaml


RUN addgroup --gid $GID $USERNAME || true && \
    adduser --uid $UID --gid $GID --disabled-password --gecos "" $USERNAME && \
    echo "$USERNAME ALL=(ALL) NOPASSWD: ALL" >> /etc/sudoers

RUN mkdir -p /home/$USERNAME/.config/fish/
RUN chown -R $UID:$GID /home/$USERNAME/
RUN echo "\n\neval (ssh-agent -c)\nssh-add\n" > /home/$USERNAME/.config/fish/config.fish 
RUN echo "\n\neval \$(ssh-agent)\nssh-add\n" > /home/$USERNAME/.bashrc
RUN chown -R $UID:$GID /home/$USERNAME/

WORKDIR /home/$USERNAME/
