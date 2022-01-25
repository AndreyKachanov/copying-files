#!/bin/bash

result=$(sqlplus -s /nolog <<EOL
connect / as sysdba
set head off
set feedback off
set pagesize 2400
set linesize 2048
select count(*) from eoda.copying_files where is_copied='0');
exit;
EOL
)
echo $result > /home/oracle/new_script2/copying-files/copying1.log
