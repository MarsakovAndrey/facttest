<?php
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
$arResult=array();
use \Bitrix\Main;
CModule::IncludeModule("iblock") and CModule::IncludeModule("sale");
class updateproduct {

    public $connection;
    function __construct()
    {
        $this->connection = Main\Application::getInstance()->getConnection();

        global $productId;
        $this->productId = $productId;

        global $passiveviev;
        $this->passiveviev = $passiveviev;

        global $activeviev;
        $this->activeviev = $activeviev;

    }
    public function getElementById(){//вернуть элемент по ид
        $element=array();
            //$connection = Main\Application::getInstance()->getConnection();

            $queryResult = $this->connection->query("SELECT * FROM marsakov_prod_raiting WHERE id = '$this->productId'");

            foreach ($queryResult as $data)
            {
                $element[$data['id']]= $data; //ключ это айди товара
            }
            return $element;

    }
    public function getInfoForRaiting(){//вернет количество фото и количество продаж для товара
        $info_for_raiting=array();
        $res = CIBlockElement::GetByID($this->productId);
        if($ar_res = $res->GetNext()) {
            $NAME = $ar_res['NAME'];
        }
        $props=CIBlockElement::GetByID($this->productId)->GetNextElement()->GetProperties();//получаю свойства
        if(count($props['MORE_PHOTO']['VALUE'])>1){//если фоток больше чем 1
            $info_for_raiting['countphoto']=1;
        } else{
            $info_for_raiting['countphoto']=0;
        }

        $arOrderIDs = Array();
        $year2Before=time() - 3600*24*365;//1 год
        global $DB;//для запроса к бд(подумать как обойти)
        $arfilter=Array (
            "!CANCELED" => "Y",//не отменены
            "@STATUS_ID" => array("AA", "QQ", ),//статус оплачен (статусы создал сам)
            ">=DATE_INSERT" => date($DB->DateFormatToPHP(CSite::GetDateFormat("SHORT")), $year2Before)//за последний год
        );
        $orders = CSaleOrder::GetList (Array(), $arfilter, false, false, Array ("ID"));//получаю заказы по нужной выборке выборке
        while ($arOrder = $orders->Fetch())
        {
            $arOrderIDs[] = $arOrder["ID"];//сложил ид заказов
        }
        $baskets = CSaleBasket::GetList (Array(), Array ("ORDER_ID" => $arOrderIDs), false, false, Array ("PRODUCT_ID", "QUANTITY","NAME"));
        $countbuy=0;//количество оплаченных товаров
        while ($arBasket = $baskets->Fetch())
        {
            if($arBasket['NAME']==$NAME){
                $countbuy=$countbuy+$arBasket['QUANTITY'];
            }
        }
        $info_for_raiting['countbuy']=$countbuy;
        return $info_for_raiting;
}
    private function updateElement(){
        $element=$this->getElementById();
        $info_for_raiting=$this->getInfoForRaiting();
        $countphoto=$info_for_raiting['countphoto'];
        $countbuy=$info_for_raiting['countbuy'];
        if($this->passiveviev==true){//если просмотр пассивный
            $passiveviev=$element[$this->productId]['passiveviev']+1;
            $activeviev=$element[$this->productId]['activeviev'];
        }
        if($this->activeviev==true){//если просмотр пассивный
            $passiveviev=$element[$this->productId]['passiveviev']-1;//отнимаю 1 тк она была добавлена во время первого запроса
            $activeviev=$element[$this->productId]['activeviev']+1;
        }
        $raiting=$activeviev+3*$countbuy+10*$countphoto-$passiveviev;//формула

        // Установим новое значение для данного свойства данного элемента
        $dbr = CIBlockElement::GetList(array(), array("=ID"=>$this->productId), false, false, array("ID", "IBLOCK_ID"));
        if ($dbr_arr = $dbr->Fetch())
        {
            $IBLOCK_ID = $dbr_arr["IBLOCK_ID"];
            CIBlockElement::SetPropertyValues($this->productId, 2, $raiting, "RAITING");
        }

        $queryResult = $this->connection->query("UPDATE marsakov_prod_raiting SET activeviev='$activeviev',passiveviev='$passiveviev',countbuy='$countbuy',countphoto='$countphoto',raiting='$raiting' WHERE id='$this->productId'");

        //return "UPDATE marsakov_prod_raiting SET activeviev='$activeviev',passiveviev='$passiveviev',countbuy='$countbuy',countphoto='$countphoto',raiting='$raiting' WHERE id='$this->productId'";
    }
    private function addElement(){
        $info_for_raiting=$this->getInfoForRaiting();
        $countphoto=$info_for_raiting['countphoto'];
        $countbuy=$info_for_raiting['countbuy'];
        if($this->passiveviev==true){//если просмотр пассивный
            $passiveviev=1;
            $activeviev=0;
        }
        if($this->activeviev==true){//если просмотр активный
            $passiveviev=0;
            $activeviev=1;
        }
        $raiting=$activeviev+3*$countbuy+10*$countphoto-$passiveviev;//формула



// Установим новое значение для данного свойства данного элемента
        $dbr = CIBlockElement::GetList(array(), array("=ID"=>$this->productId), false, false, array("ID", "IBLOCK_ID"));
        if ($dbr_arr = $dbr->Fetch())
        {
            $IBLOCK_ID = $dbr_arr["IBLOCK_ID"];
            CIBlockElement::SetPropertyValues($this->productId, 2, $raiting, "RAITING");
        }



        $queryResult = $this->connection->query("INSERT INTO marsakov_prod_raiting (id, activeviev, passiveviev,countbuy,countphoto,raiting) VALUES ('$this->productId', '$activeviev', '$passiveviev','$countbuy','$countphoto','$raiting')");
        //return "INSERT INTO marsakov_prod_raiting (id, activeviev, passiveviev,countbuy,countphoto,raiting) VALUES ('$this->productId', '$activeviev', '$passiveviev','$countbuy','$countphoto','$raiting')";
    }

    public function start_update(){//начало пересчета рейтинга
        $haveel=$this->getElementById($this->id);
        if(count($haveel)>0){//если товар в таблице есть то обновим
            $result_update=$this->updateElement();
        }
        else{//если товара нет то надо создать и посчитать
            $result_update=$this->addElement();
        }
        return $result_update;
    }
}
$work=new updateproduct($_POST['productId'],$_POST['activeviev'],$_POST['passiveviev']);

$res=$work->start_update();


    $arResult['result'] = $res;
    $arResult['post'] = $_POST;

echo json_encode($arResult);//для теста проверить данные в консоли
