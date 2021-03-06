<?php

namespace DataWarehouse\Query\Jobs\GroupBys;

/* 
* @author Amin Ghadersohi
* @date 2011-Jan-07
*
* class for adding group by institution to a query
* 
*/
class GroupByPIInstitution extends \DataWarehouse\Query\Jobs\GroupBy
{
	public static function getLabel()
	{
		 return  'PI Institution';
	}

	public function getDefaultDatasetType()
	{
		return 'aggregate';
	}
	public function getDefaultDisplayType($dataset_type = NULL)
	{
		return 'datasheet';
	}
	public function getInfo()
	{
		return 	"Organizations that have PIs with allocations.";
	}

	public function __construct()
	{
		parent::__construct('pi_institution', array(), 
		"SELECT distinct
			gt.id, 
			gt.short_name as short_name, 
			gt.long_name as long_name
		FROM 
			organization gt
		where 1
		order by gt.order_id");
		
		$this->_id_field_name = 'id';
		$this->_long_name_field_name = 'long_name';
		$this->_short_name_field_name = 'short_name';								
		$this->_order_id_field_name = 'order_id';
		$this->modw_schema = new \DataWarehouse\Query\Model\Schema('modw');
		$this->organization_table = new \DataWarehouse\Query\Model\Table($this->modw_schema, 'organization', 'o');
	}
	public function applyTo(\DataWarehouse\Query\Query &$query, \DataWarehouse\Query\Model\Table $data_table, $multi_group = false)
	{		
		$query->addTable($this->organization_table);
				
		$organization_id_field = new \DataWarehouse\Query\Model\TableField($this->organization_table, $this->_id_field_name, $this->getIdColumnName($multi_group));
		$organization_name_field = new \DataWarehouse\Query\Model\TableField($this->organization_table, $this->_long_name_field_name, $this->getLongNameColumnName($multi_group));
		$organization_abbrev_field = new \DataWarehouse\Query\Model\TableField($this->organization_table, $this->_short_name_field_name, $this->getShortNameColumnName($multi_group));
		$order_id_field = new \DataWarehouse\Query\Model\TableField($this->organization_table,$this->_order_id_field_name, $this->getOrderIdColumnName($multi_group));
		
		$query->addField($order_id_field);
		$query->addField($organization_id_field);		
		$query->addField($organization_name_field);
		$query->addField($organization_abbrev_field);
		
		$query->addGroup($organization_id_field);		
		
		$datatable_pi_person_id_field = new \DataWarehouse\Query\Model\TableField($data_table,'piperson_organization_id');
		$query->addWhereCondition(new \DataWarehouse\Query\Model\WhereCondition($organization_id_field, '=', $datatable_pi_person_id_field));		
		
		$this->addOrder($query,$multi_group);
	}
	
	public function addWhereJoin(\DataWarehouse\Query\Query &$query, 
                                 \DataWarehouse\Query\Model\Table $data_table, 
                                 $multi_group = false,
                                 $operation,
                                 $whereConstraint)
	{		

        // construct the join between the main data_table and this group by table
		$query->addTable($this->organization_table);
				
		$organization_id_field = new \DataWarehouse\Query\Model\TableField($this->organization_table, $this->_id_field_name);
		$datatable_pi_person_id_field = new \DataWarehouse\Query\Model\TableField($data_table,'piperson_organization_id');

        // the where condition that specifies the join of the tables
		$query->addWhereCondition(new \DataWarehouse\Query\Model\WhereCondition($organization_id_field, '=', $datatable_pi_person_id_field));		

        // the where condition that specifies the constraint on the joined table
        if (is_array($whereConstraint)) $whereConstraint="(". implode(",",$whereConstraint) .")";

        $query->addWhereCondition(
            new \DataWarehouse\Query\Model\WhereCondition(
                $organization_id_field,
                $operation,
                $whereConstraint
            )
        );
	}
		
	public function addOrder(\DataWarehouse\Query\Query &$query, $multi_group = false, $dir = 'asc', $prepend = false)
	{
		$orderField = new \DataWarehouse\Query\Model\OrderBy(new \DataWarehouse\Query\Model\TableField($this->organization_table, $this->_order_id_field_name), $dir, $this->getName());
		if($prepend === true)
		{
			$query->prependOrder($orderField);
		}else
		{
			$query->addOrder($orderField);
		}
	}
	public function pullQueryParameters(&$request)
	{	
		return parent::pullQueryParameters2($request,'_filter_', 'piperson_organization_id');
		/*$parameters = array();
		if(isset($request[$this->getName().'_filter']) && $request[$this->getName().'_filter'] != '')
		{
			$filterString = $request[$this->getName().'_filter'];
			
			$filterItems = explode(',',$filterString);
			
			if(isset($request[$this->getName()])) 
			{
				$filterItems[] = $request[$this->getName()];
			}
			
			if(count($filterItems) > 0) $parameters[] = new \DataWarehouse\Query\Model\Parameter('person_organization_id', 'in', "(".implode(',',$filterItems).")");		
		}
		else
		if(isset($request[$this->getName()]))
		{
			$parameters[] = new \DataWarehouse\Query\Model\Parameter('person_organization_id', '=', $request[$this->getName()]);		
			
		}
		return $parameters;*/
	}	
	public function pullQueryParameterDescriptions(&$request)
	{
		return parent::pullQueryParameterDescriptions2($request, 
							"select short_name as field_label from modw.organization  where id in (_filter_) order by order_id");
	}
	
	public function getPossibleValues($hint = NULL, $limit = NULL, $offset = NULL, array $parameters = array())
	{
		if($this->_possible_values_query == NULL)
		{
			return array();
		}
		
		$possible_values_query = $this->_possible_values_query;
		
		foreach($parameters as $pname => $pvalue)
		{
			if($pname == 'person')
			{				
				$possible_values_query = str_ireplace('from ', "from modw.peopleunderpi pup, modw.piperson pip, ",$possible_values_query);
				$possible_values_query = str_ireplace('where ', "where pup.principalinvestigator_person_id = pip.person_id and pip.organization_id = gt.id and pup.person_id = $pvalue and ",$possible_values_query);
			}else
			if($pname == 'provider')//find the institutions of all the pis that have accounts on the resources at the provider.
			{
				$possible_values_query = str_ireplace('from ', "from modw.piperson p, modw.systemaccount sa,  modw.resourcefact rf,  ",$possible_values_query);
				$possible_values_query = str_ireplace('where ', "where rf.id = sa.resource_id and rf.organization_id = $pvalue and p.person_id = sa.person_id and p.organization_id = gt.id  and ",$possible_values_query);
			
			}else
			if($pname == 'institution')
			{
				$possible_values_query = str_ireplace('where ', "where gt.id = $pvalue and ",$possible_values_query);
			}else
			if($pname == 'pi')
			{
				$possible_values_query = str_ireplace('from ', "from modw.piperson pip, ",$possible_values_query);
				$possible_values_query = str_ireplace('where ', "where pip.organization_id = gt.id and pip.person_id = $pvalue and ",$possible_values_query);
			}
		}
		
		return parent::getPossibleValues($hint,$limit,$offset,$parameters,$possible_values_query);
	}
}
?>
