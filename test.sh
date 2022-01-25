#!/bin/bash

result=$(sqlplus -s /nolog <<EOL
connect / as sysdba
select count(*) from eoda.copying_files where is_copied='0');
exit;
EOL
)
echo $result
