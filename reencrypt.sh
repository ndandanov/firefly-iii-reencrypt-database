#!/bin/bash

# Configuration
# Caution: use secure credentials!
# Change any names where appropriate
db_user="root"
db_pass="123456"
db_name="firefly_db"

# Declare the arrays, which contain the fields and tables to be optimized
declare -a fields=("name" "name" "match" "name" "data" "tag" "description" "description");
declare -a tables=("accounts" "bills" "bills" "categories" "preferences" "tags" "tags" "transaction_journals" );
# The above means, that all records for the column "name" from table "accounts" will be processed first.
# Then, all records for the column "name" from table "match" will be processed.
# And so on.

# Original fields I used:
#declare -a fields=("name" "iban" "name" "match" "name" "data" "tag" "description" "description");
#declare -a tables=("accounts" "accounts" "bills" "bills" "categories" "preferences" "tags" "tags" "transaction_journals" );

# The numbers of fields and tables records to iterate over is:
fields_num=${#fields[@]};
tables_num=${#tables[@]};
if [ $fields_num -ne $tables_num ]
then
  echo "The numbers of fields and tables records to iterate over do not match!"
fi

# The original key, with which the database records are encrypted.
key="SomeRandomStringOf32CharsExactly";

# The new key, encoded with base64
# (just as supplied from the .env file in the new Firefly III installation)
encodedNewKey="TXlOZXdSYW5kb21TdHJpbmdPZjMyQ2hhckV4YWN0bHkK";
# Decode the new key
newKey=`echo -n $encodedNewKey | base64 --decode`;

# Process the data
# Get the data from the database
for (( c=0; c<$fields_num; c++ ))
do
  echo "Beginning to reencrypt the $[$c+1]. field-table combination:"
  echo "field is ${fields[$c]}"
  echo "table is ${tables[$c]}"
  i=0;
  # Reencrypt row by row
  while read -a row
  do
    encryptedData[$i]=$row;

    # Reencrypt the encryptedData with the new key
    # Here we use a slightly modified Laravel Crypt encryptor/decryptor script,
    # original by:
    # https://gist.github.com/leonjza/ce27aa7435f8d131d93f
    phpOut=`php crypt.php ${encryptedData[$i]} $key $newKey`;

    # Parse the PHP JSON output
    decryptedData=`echo -n $phpOut | jq --raw-output '.decrypted_data'`
    reencryptedData=`echo -n $phpOut | jq --raw-output '.reencrypted_data'`

    # Debug info:
    #echo $decryptedData
    #echo $reencryptedData

    # Update the data in the database, in-place
    mysql -s -u $db_user -p$db_pass $db_name \
    -e "UPDATE ${tables[$c]} SET \`${fields[$c]}\`='$reencryptedData' WHERE \`${fields[$c]}\`='${encryptedData[$i]}' ;"

    i=$[$i+1];
  done < <(mysql -s -N -u $db_user -p$db_pass $db_name \
  -e "SELECT \`${fields[$c]}\` FROM ${tables[$c]};")
  # For a remote database, you can use this:
  # mysql -s -N -h $srv_db_ip --port=$srv_db_port -u $srv_db_user -p$srv_db_pass $srv_db_name -e "SELECT $field FROM $table WHERE $condition;"
done
