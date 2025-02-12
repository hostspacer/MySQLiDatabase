# MySQLiDatabase Use

### Explanation:

#### Insert Data:
The function ```insertData``` inserts a new record into the specified table.
##### Usage Example:
```php
// Data to be inserted
$data = [
    'column1' => 'value1',
    'column2' => 'value2',
    // Add more columns and values as needed
];

// Insert data and get the last inserted ID
$lastInsertId = insertData('your_table_name', $data);

if ($lastInsertId) {
    echo "Data inserted successfully. Last Inserted ID: " . $lastInsertId;
} else {
    echo "Failed to insert data.";
}
```


#### Update Data:
The function ```updateData``` updates existing records in the specified table.
        
#### Delete Data:
The function ```deleteData``` deletes records from the specified table.

#### Select Data:
The function ```selectData``` deletes records from the specified table.
##### Usage Example:
```php
// Select data with conditions
$conditions = [
    'column1' => 'value1',
    'column2' => 42,  // Mixed data types
];

$data = selectData('your_table_name', $conditions, '', '*', true);

if ($data) {
    print_r($data); // Output the data
} else {
    echo "No data found.";
}
```




