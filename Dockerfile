FROM wordpress:5.5.1

ADD .devcontainer/install-compose.sh /var/www/html
RUN chmod +x /var/www/html/install-compose.sh 
RUN apt-get update 
RUN apt-get install wget -y 
RUN ./install-compose.sh 