<?php namespace koolreport\querybuilder;

class QueryTest extends \Codeception\Test\Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    // tests
    public function testSelectRaw()
    {
        $sql =  DB::table('orders')->selectRaw('price * ? as price_with_tax', [1.0825])->toMySQL();
        $this->assertEquals($sql, "SELECT price * 1.0825 as price_with_tax FROM orders");
    }

    public function testCoverIdentity()
    {
        $sql = DB::table('orders')->select('orders.id')->toMySQL(true);
        $this->assertEquals($sql, "SELECT `orders`.`id` FROM `orders`");
    }

    public function testNoCoverIdentity()
    {
        $sql = DB::table('orders')->select('orders.id')->toMySQL();
        $this->assertEquals($sql, "SELECT orders.id FROM orders");

        $sql = MySQL::type(
            DB::table('orders')->select('orders.id')
        );
        $this->assertEquals($sql, "SELECT orders.id FROM orders");
    }

    public function testSetCoverEntity()
    {
        $sql = DB::table('orders')->select('orders.id')->toMySQL(['[',']']);
        $this->assertEquals($sql, "SELECT [orders].[id] FROM [orders]");
    }

    public function testSQLServerLimitAndOffset()
    {
        $sql = DB::table('orders')
        ->select('orders.id')
        ->limit(10)
        ->offset(10)
        ->toSQLServer();
        $this->assertEquals($sql, "SELECT orders.id FROM orders OFFSET 10 ROWS FETCH NEXT 10 ROWS ONLY");
    }

    public function testGroupBy()
    {
        $sql = DB::table('orders')
        ->groupBy('name')->toMySQL();
        $this->assertEquals($sql, "SELECT * FROM orders GROUP BY name");

        $sql2 = DB::table('orders')
        ->groupBy('DAY(created_time)')->toMySQL();
        $this->assertEquals($sql2, "SELECT * FROM orders GROUP BY DAY(created_time)");

        $sql3 = DB::table('orders')
        ->groupByRaw('DAY(created_time)')->toMySQL(true);
        $this->assertEquals($sql3, "SELECT * FROM `orders` GROUP BY DAY(created_time)");
    }

    public function testCreate()
    {
        $query = Query::create([
            "type"=>"select",
            "tables"=>["orders"],
            "groups"=>["name"],
            "limit"=>2
        ]);
        $sql = $query->toMySQL();
        $this->assertEquals("SELECT * FROM orders LIMIT 2",$sql);
    }

    public function testToArray()
    {
        $query = Query::create([
            "type"=>"select",
            "tables"=>["orders"],
            "limit"=>2,
            "offset"=>3,
            "distinct"=>true,
            "lock"=>true,
        ]);
        $str_arr = json_encode($query->toArray());
        $this->assertEquals("abc",$str_arr);
    }

    public function testSerialize()
    {
        $st = '{"type":"select","tables":["orders",[{"type":"select","tables":["orderdetails"],"columns":[["amount"]],"conditions":[],"orders":[],"groups":[],"having":null,"limit":null,"offset":null,"joins":[],"distinct":false,"unions":[],"values":[],"lock":null},"t"]],"columns":[["name","firstName"]],"conditions":[],"orders":[],"groups":["name"],"having":null,"limit":null,"offset":null,"joins":[["JOIN","customers",{"type":"select","tables":[],"columns":[],"conditions":[["customerId","=","[{colName}]id"]],"orders":[],"groups":[],"having":null,"limit":null,"offset":null,"joins":[],"distinct":false,"unions":[],"values":[],"lock":null}]],"distinct":false,"unions":[],"values":[],"lock":null}';

        $query = Query::create(json_decode($st,true));
        $serial = json_encode($query->toArray());
        $this->assertEquals($serial, $st);
    }

}