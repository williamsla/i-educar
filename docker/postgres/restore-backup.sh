#!/bin/bash

echo 'criando banco de dados'
psql -U postgres -c "CREATE ROLE ieducar WITH LOGIN PASSWORD 'ieducar';"
psql -U postgres -c "CREATE DATABASE ieducar OWNER ieducar;" 

echo 'restaurando banco de dados'
psql -U ieducar --dbname ieducar --file ../backup/ieducar.sql
