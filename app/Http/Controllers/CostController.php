<?php
namespace app\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

/*
 * 
 * Author : PrashanR
 * Name : Cost Controller
 * Controller will be used to calculate the breakdown by client and projects. 
 */
class CostController extends Controller
{
 
    public function explorer(){
        $params = array();
        $params = $_GET;
        
        if(isset($params['projects']) & isset($params['clients'])) {
            $resulsSet = $this->getClientsBreakDown($params['clients'],$params['projects'],null); 
        }else if(isset($params['cost_types'])) {
            $resulsSet = $this->getClientsBreakDown(null, null, $params['cost_types']); 

        }else if (isset($params['clients'])){
            
            $resulsSet = $this->getClientsBreakDown($params['clients'],null,null);   
        }else {
            $resulsSet = $this->getClientsBreakDown(null, null,null);
        }
        return $resulsSet;
    }
    
    /*
     * getClientsBreakDown
     * 
     * @param array $clients - Client Input Array.
     * @param array $projects - Project Input Array
     * @param array $costTypeList - Cost Type List 
     * @return type Client Breakdown.
     * 
     */
    private function getClientsBreakDown($clients,$projects,$costTypes){
        $clientInWhereClause= "";
        $projectsInWhereClause= "";
        $costTypeInWhereClause = ""; 
        if(is_array($clients) && sizeof($clients)>0) {
            $clientInWhereClause = " WHERE id in (". implode (",",$clients).")";
        }
        
        if(is_array($projects) && sizeof($projects)>0) {
            $projectsInWhereClause = " and id in (". implode (",",$projects).")";
        }
        
        if(is_array($costTypes) && sizeof($costTypes)>0) {
            $costTypeInWhereClause = " and ctype.id in (". implode (",",$costTypes).")";
        }
        
        $clients= DB::select('select  * from clients'.$clientInWhereClause);
        $index = 0;
        foreach($clients as $client) {
            $amount = 0;
            $projects= DB::select('select prj.id as id, prj.Title as name, sum(cst.Amount) as amount from projects prj join costs cst on prj.client_id = ? and prj.id = cst.Project_ID join cost_types ctype on cst.Cost_Type_ID = ctype.id  and ctype.Parent_Cost_Type_ID is null  group by prj.id, prj.title order by prj.id,ctype.id asc '.$projectsInWhereClause,[$client->ID]);
            foreach ($projects as $project) {
                $amount+=  $project->amount;
                $project->breakdown = $this->getCostBrekDownByProjectId($project->id, null,$costTypeInWhereClause);
            }
            if(sizeof($projects)<=0) {
                unset($clients[$index]);
            }
            $client->amount = $amount;
            $client->breakdown = $projects;
            $index++;
        }
        return $clients;        
    }
    
    /*
     * getCostBrekDownByProjectId
     *
     * @param int $projectId - Project Id.
     * @param int $parentTypeId - Parent  Cost Type id
     * @costTypeSubQuery String - SubQuery
     * @return Array Breakdown by cost-type-id.
     *
     */
       
    
    private function getCostBrekDownByProjectId($projectId, $parentCostTypeId,$costTypeSubQuery){
        $parentTypeSqlString = "";        
        if($parentCostTypeId) {            
            $parentTypeSqlString = " ctype.Parent_Cost_Type_ID = $parentCostTypeId" ;   
        }else {
            $parentTypeSqlString = " ctype.Parent_Cost_Type_ID is null" ;         
        }
        $sqlQuery = 'select  ctype.id as id, ctype.Name as name, cst.amount as amount from costs cst left join cost_types ctype on cst.cost_type_id = ctype.id where'.$parentTypeSqlString.' and cst.project_id='.$projectId.' '.$costTypeSubQuery;
        
        $projectBreakDownByParentCostType= DB::select($sqlQuery);
        if($projectBreakDownByParentCostType) {
            foreach ($projectBreakDownByParentCostType as $breakdown) {
                $breakdown->breakdown = $this->getCostBrekDownByProjectId($projectId,$breakdown->id,""); 
            }
        }else {
            return ;
        }
        return $projectBreakDownByParentCostType;        
    }
    
}

