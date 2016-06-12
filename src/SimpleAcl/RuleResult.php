<?php
namespace SimpleAcl;

use SimpleAcl\Resource\ResourceAggregateInterface;
use SimpleAcl\Role\RoleAggregateInterface;

/**
 * Returned as result of Rule::isAllowed
 *
 * @package SimpleAcl
 */
class RuleResult
{
  /**
   * @var Rule
   */
  protected $rule;

  /**
   * @var string
   */
  protected $needRoleName;

  /**
   * @var string
   */
  protected $needResourceName;

  /**
   * @var int
   */
  protected $priority;

  /**
   * @var string
   */
  protected $id;

  /**
   * @var
   */
  protected $action;

  /**
   * @var bool
   */
  protected $isInit = false;

  /**
   * @param Rule $rule
   * @param int  $priority
   * @param      $needRoleName
   * @param      $needResourceName
   */
  public function __construct(Rule $rule, $priority, $needRoleName, $needResourceName)
  {
    static $idCountRuleResultSimpleAcl = 1;

    $this->id = $idCountRuleResultSimpleAcl++;
    $this->rule = $rule;
    $this->priority = $priority;
    $this->needRoleName = $needRoleName;
    $this->needResourceName = $needResourceName;
  }

  /**
   * @param int $priority
   */
  public function setPriority($priority)
  {
    $this->priority = $priority;
  }

  /**
   * @return string
   */
  public function getNeedResourceName()
  {
    return $this->needResourceName;
  }

  /**
   * @return string
   */
  public function getNeedRoleName()
  {
    return $this->needRoleName;
  }

  /**
   * @return Rule
   */
  public function getRule()
  {
    return $this->rule;
  }

  /**
   * @return bool
   */
  public function getAction()
  {
    if (!$this->isInit) {
      $this->action = $this->getRule()->getAction($this);
      $this->isInit = true;
    }

    return $this->action;
  }

  /**
   * @return int
   */
  public function getPriority()
  {
    return $this->priority;
  }

  /**
   * @return string
   */
  public function getId()
  {
    return $this->id;
  }

  /**
   * @return ResourceAggregateInterface
   */
  public function getResourceAggregate()
  {
    return $this->getRule()->getResourceAggregate();
  }

  /**
   * @return RoleAggregateInterface
   */
  public function getRoleAggregate()
  {
    return $this->getRule()->getRoleAggregate();
  }
}
