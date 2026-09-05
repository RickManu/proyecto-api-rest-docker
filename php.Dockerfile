FROM php:8.2-apache

# La imagen base no trae mysqli habilitado por defecto: hay que compilarlo
RUN docker-php-ext-install mysqli
