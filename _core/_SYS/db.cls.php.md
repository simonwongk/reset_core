# SELECT * FROM table1 WHERE name="tom" order by age desc,address
## db::s( 'table1', [ 'name' => 'tom' ], 'age desc,address' );

# SELECT * FROM table1 WHERE name="tom" AND age=30
`PHP
$cond = [
	'name' => 'tom',
	'age' => 30
];
db::s( 'table1', $cond );
`

# SELECT * FROM table1 WHERE name="tom" OR age=30
`PHP
$cond = [
	'_or' => [
		'name' => 'tom',
		'age' => 30,
	]
];
db::s( 'table1', $cond );
`

# SELECT * FROM table1 WHERE id=3
## db::s( 'table1', 3 );
## db::s( 'table1', [ 3 ] );
## db::s( 'table1', [ 'id' => 3 ] );

# SELECT * FROM table1 WHERE name IN ("tom", "jack")
`PHP
$cond = [
	'name' => [
		'in',
		[ 'tom', 'jack' ]
	]
];
db::s( 'table1', $cond );
`

# SELECT * FROM table1 WHERE age BETWEEN 5 AND 20
## db::s( 'table1', [ 'age' => [ 'between', 5, 20 ] ] );

# SELECT * FROM table1 WHERE name LIKE '%tom%'
## db::s( 'table1', [ 'name' => [ 'like', '%tom%' ] ] );

# SELECT age,gender FROM table1 WHERE name="tom"
## db::s( [ 'age,gender', 'table1' ], [ 'name' => 'tom' ] );

# SELECT * FROM table1 WHERE age=30 order by address limit 10
## db::sa( 'table1', [ age' => 30 ], 'address', 10 );

# UPDATE table1 set age=31,city='NYC' where name="tom"
## db::u( 'table1', [ 'age' => 31, 'city' => 'NYC' ], [ 'name' => 'tom' ] );

# INSERT INTO table1 set name="tom",age="30",gender="male"
## db::i( 'table1', [ 'name' => 'tom', 'age' => 30, 'gender' => 'male' ] );

# DELETE FROM table1 where name="tom"
## db::d( 'table1', [ 'name' => 'tom' ] );