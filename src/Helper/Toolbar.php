<?php

namespace ORM\Helper;

use ORM\DB\DBFactory;
use Zend\View\Helper\AbstractHelper;

class Toolbar extends AbstractHelper
{

    public function __invoke()
    {

        if(getenv("APPLICATION_ENVIRONMENT") !== "development"
            OR isset($_GET['hide_bar']) AND $_GET['hide_bar'] == "true"){
            return;
        }

        $queries = DBFactory::getAllProfiledQuery();

        $tempo_totale = 0;
        $numero_totale_query = 0;
        foreach ($queries as $hash => $query) {
            $tempo_totale += $query["total_time"];
            $numero_totale_query += $query["counter"];
        }


        return $this->view->partial('toolbar/toolbar.phtml', [
            'queries' => $queries,
            'tempo_totale' => $tempo_totale,
            'numero_totale_query' => $numero_totale_query
        ]);
    }
}