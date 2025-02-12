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

```php
// Usage example
$data = [
    'column1' => 'new_value1',
    'column2' => 100.50,
];

$conditions = [
    'id' => 42,
];

$updateSuccess = updateData('your_table_name', $data, $conditions);

if ($updateSuccess) {
    echo "Data updated successfully.";
} else {
    echo "Failed to update data.";
}
```

        
#### Delete Data:
The function ```deleteData``` deletes records from the specified table.

```php
// Usage example
$conditions = [
    'id' => 42,
];

$deleteSuccess = deleteData('your_table_name', $conditions);

if ($deleteSuccess) {
    echo "Data deleted successfully.";
} else {
    echo "Failed to delete data.";
}
```

#### Select Data:
The function ```selectData``` deletes records from the specified table.
##### Usage Example for array result:
```php
// Select data with conditions
$conditions = [
    'column1' => 'value1',
    'column2' => 42,  // Mixed data types
];

$data = selectData('your_table_name', $conditions, true);

if ($data) {
    foreach ($data as $row){
        echo $row['column1'] . "<br>";
        echo $row['column2'] . "<br>";
    }
} else {
    echo "No data found.";
}
```

##### Usage Example for object result:
```php
// Select data with conditions
$conditions = [
    'column1' => 'value1',
    'column2' => 42,  // Mixed data types
];

$data = selectData('your_table_name', $conditions);

if ($data) {
    foreach ($data as $row){
        echo $row->column1 . "<br>";
        echo $row->column2 . "<br>";
    }
} else {
    echo "No data found.";
}
```

##### Usage Example using join:
```php
// Select data with conditions
$conditions = [
    'column1' => 'value1',
    'column2' => 42,  // Mixed data types
];
$join = 'INNER JOIN your_table_name2 ON column1=your_table_name2.column';
$data = selectData('your_table_name', $conditions, false, $join);

if ($data) {
    foreach ($data as $row){
        echo $row->column1 . "<br>";
        echo $row->column2 . "<br>";
         echo $row->column;
    }
} else {
    echo "No data found.";
}
```

## Parse Raw SQLi Statement 
It will automatically convert your raw sql statement into a secure statement and then execute it for result.
### Usage Examples:

#### Insert Data

```php
$rawSql = "INSERT INTO your_table_name (column1, column2) VALUES ('" . $_POST['name'] . "', '" . $_POST['email'] . "')";
$insertSuccess = parseRawQuery($rawSql);

if ($insertSuccess) {
    echo "Data inserted successfully.";
} else {
    echo "Failed to insert data.";
}
```

#### Update Data

```php
$rawSql = "UPDATE your_table_name SET column1 = '" . $_POST['name'] . "', column2 = '" . $_POST['email'] . "' WHERE id = " . $_POST['id'];
$updateSuccess = parseRawQuery($rawSql);

if ($updateSuccess) {
    echo "Data updated successfully.";
} else {
    echo "Failed to update data.";
}
```

#### Delete Data

```php
$rawSql = "DELETE FROM your_table_name WHERE id = " . $_POST['id'];
$deleteSuccess = parseRawQuery($rawSql);

if ($deleteSuccess) {
    echo "Data deleted successfully.";
} else {
    echo "Failed to delete data.";
}
```

#### Select Data

```php
$rawSql = "SELECT * FROM your_table_name ORDER BY column1 DESC";
$rows = parseAndExecuteRawQuery($rawSql);

if ($rows) {
    print_r($rows); // Output the rows
} else {
    echo "No data found.";
}
```



