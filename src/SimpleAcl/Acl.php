<?php

namespace SimpleAcl;

use SimpleAcl\Exception\InvalidArgumentException;
use SimpleAcl\Exception\RuntimeException;
use SimpleAcl\Resource\ResourceAggregateInterface;
use SimpleAcl\Role\RoleAggregateInterface;

/**
 * Access Control List (ACL) management.
 *
 * @package SimpleAcl
 */
class Acl
{
  /**
   * Contains registered rules.
   *
   * @var Rule[]
   */
  protected $rules = [];

  /**
   * Class name used when rule created from string.
   *
   * @var string
   */
  protected $ruleClass = 'SimpleAcl\Rule';

  /**
   * Adds rule.
   *
   * Assign $role, $resource and $action to added rule.
   * If rule was already registered only change $role, $resource and $action for that rule.
   *
   * This method accept 1, 2, 3 or 4 arguments:
   *
   * addRule($rule)
   * addRule($rule, $action)
   * addRule($role, $resource, $rule)
   * addRule($role, $resource, $rule, $action)
   *
   * @param Role     ..$role
   * @param Resource ..$resource
   * @param Rule|string ..$rule
   * @param mixed    ..$action
   *
   * @throws InvalidArgumentException
   */
  public function addRule()
  {
    $args = \func_get_args();
    $argsCount = \count($args);

    $role = null;
    $resource = null;
    $action = null;

    if ($argsCount == 4 || $argsCount == 3) {

      $role = $args[0];
      $resource = $args[1];
      $rule = $args[2];

      if ($argsCount == 4) {
        $action = $args[3];
      }

    } elseif ($argsCount == 2) {

      $rule = $args[0];
      $action = $args[1];

    } elseif ($argsCount == 1) {
      $rule = $args[0];
    } else {
      throw new InvalidArgumentException(__METHOD__ . ' accepts only one, tow, three or four arguments');
    }

    if (
        null !== $role
        &&
        !$role instanceof Role
    ) {
      throw new InvalidArgumentException('Role must be an instance of SimpleAcl\Role or null');
    }

    if (
        null !== $resource
        &&
        !$resource instanceof Resource
    ) {
      throw new InvalidArgumentException('Resource must be an instance of SimpleAcl\Resource or null');
    }

    if (\is_string($rule)) {
      $ruleClass = $this->getRuleClass();
      $rule = new $ruleClass($rule);
    }

    if (!$rule instanceof Rule) {
      throw new InvalidArgumentException('Rule must be an instance of SimpleAcl\Rule or string');
    }

    $exchange = $this->hasRule($rule);

    if ($exchange) {
      $rule = $exchange;
    } else {
      $this->rules[] = $rule;
    }

    if ($argsCount == 3 || $argsCount == 4) {
      $rule->setRole($role);
      $rule->setResource($resource);
    }

    if ($argsCount == 2 || $argsCount == 4) {
      $rule->setAction($action);
    }
  }

  /**
   * Return rule class.
   *
   * @return string
   */
  public function getRuleClass(): string
  {
    return $this->ruleClass;
  }

  /**
   * Set rule class.
   *
   * @param string $ruleClass
   */
  public function setRuleClass(string $ruleClass)
  {
    if (!class_exists($ruleClass)) {
      throw new RuntimeException('Rule class not exist');
    }

    if (
        $ruleClass != 'SimpleAcl\Rule'
        &&
        !is_subclass_of($ruleClass, 'SimpleAcl\Rule')
    ) {
      throw new RuntimeException('Rule class must be instance of SimpleAcl\Rule');
    }

    $this->ruleClass = $ruleClass;
  }

  /**
   * Return true if rule was already added.
   *
   * @param Rule|mixed $needRule Rule or rule's id
   *
   * @return bool|Rule
   */
  public function hasRule($needRule)
  {
    if ($needRule instanceof Rule) {
      $needRuleId = $needRule->id;
    } else {
      $needRuleId = $needRule;
    }

    foreach ($this->rules as $rule) {
      if ($rule->id === $needRuleId) {
        return $rule;
      }
    }

    return false;
  }

  /**
   * Checks is access allowed.
   *
   * @param string|RoleAggregateInterface     $roleName
   * @param string|ResourceAggregateInterface $resourceName
   * @param string                            $ruleName
   *
   * @return bool
   */
  public function isAllowed($roleName, $resourceName, $ruleName)
  {
    return $this->isAllowedReturnResult($roleName, $resourceName, $ruleName)->get();
  }

  /**
   * Simple checks is access allowed.
   *
   * @param string|RoleAggregateInterface     $roleAggregate
   * @param string|ResourceAggregateInterface $resourceAggregate
   * @param string                            $ruleName
   * @param RuleResultCollection              $ruleResultCollection
   *
   * @return RuleResultCollection|null null if there wasn't a clear result
   */
  protected function isAllowedReturnResultSimple($roleAggregate, $resourceAggregate, $ruleName, $ruleResultCollection)
  {
    if (
        \is_string($ruleName)
        &&
        \is_string($roleAggregate)
        &&
        \is_string($resourceAggregate)
    ) {

      foreach ($this->rules as $ruleTmp) {

        // INFO: we can't use "getName()" here, because of performance issue
        if ($ruleTmp->name !== $ruleName) {
          continue;
        }

        $resourceTmp = $ruleTmp->getResource();
        $roleTmp = $ruleTmp->getRole();

        if (
            (
                $resourceTmp instanceof Resource
                &&
                $resourceTmp->getName() === $resourceAggregate
            )
            &&
            (
                $roleTmp instanceof Role
                &&
                $roleTmp->getName() === $roleAggregate
            )
        ) {
          $resultTmp = $ruleTmp->isAllowed($ruleName, $roleAggregate, $resourceAggregate);

          if ($resultTmp && null === $resultTmp->getAction()) {
            unset($resultTmp);
          } else {
            // Set null if rule don't match any role or resource.
            $ruleResultCollection->add($resultTmp);

            return $ruleResultCollection;
          }
        }
      }
    }

    return null;
  }

  /**
   * Checks is access allowed.
   *
   * @param string|RoleAggregateInterface     $roleAggregate
   * @param string|ResourceAggregateInterface $resourceAggregate
   * @param string                            $ruleName
   *
   * @return RuleResultCollection
   */
  public function isAllowedReturnResult($roleAggregate, $resourceAggregate, $ruleName)
  {
    $ruleResultCollection = new RuleResultCollection();

    $tmpResult = $this->isAllowedReturnResultSimple($roleAggregate, $resourceAggregate, $ruleName, $ruleResultCollection);
    if ($tmpResult !== null) {
      return $tmpResult;
    }

    $roles = $this->getNames($roleAggregate);
    $resources = $this->getNames($resourceAggregate);

    foreach ($roles as $roleName) {
      foreach ($resources as $resourceName) {
        foreach ($this->rules as $rule) {

          if (
              \is_string($ruleName)
              &&
              !is_subclass_of($rule, 'SimpleAcl\Rule')
              &&
              $rule->getName() !== $ruleName
          ) {
            continue;
          }

          $rule->resetAggregate($roleAggregate, $resourceAggregate);

          $result = $rule->isAllowed($ruleName, $roleName, $resourceName);

          // Set null if rule don't match any role or resource.
          $ruleResultCollection->add($result);
        }
      }
    }

    return $ruleResultCollection;
  }

  /**
   * Get names.
   *
   * @param string|RoleAggregateInterface|ResourceAggregateInterface $object
   *
   * @return array
   */
  protected function getNames($object): array
  {
    if (\is_string($object) || null === $object) {
      return [$object];
    }

    if ($object instanceof RoleAggregateInterface) {
      return $object->getRolesNames();
    }

    if ($object instanceof ResourceAggregateInterface) {
      return $object->getResourcesNames();
    }

    return [];
  }

  /**
   * Remove rules by rule name and (or) role and resource.
   *
   * @param null|string $roleName
   * @param null|string $resourceName
   * @param null|string $ruleName
   * @param bool        $all
   */
  public function removeRule($roleName = null, $resourceName = null, $ruleName = null, bool $all = true)
  {
    if (
        null === $roleName
        &&
        null === $resourceName
        &&
        null === $ruleName
    ) {
      $this->removeAllRules();

      return;
    }

    foreach ($this->rules as $ruleIndex => $rule) {
      if (
          (
              $ruleName === null
              ||
              (
                  $ruleName !== null
                  &&
                  $ruleName === $rule->getName()
              )
          )
          &&
          (
              $roleName === null
              ||
              (
                  $roleName !== null
                  &&
                  $rule->getRole()
                  &&
                  $rule->getRole()->getName() === $roleName
              )
          )
          &&
          (
              $resourceName === null
              ||
              (
                  $resourceName !== null
                  &&
                  $rule->getResource()
                  &&
                  $rule->getResource()->getName() === $resourceName
              )
          )
      ) {

        unset($this->rules[$ruleIndex]);
        if (!$all) {
          return;
        }

      }
    }
  }

  /**
   * Remove all rules.
   */
  public function removeAllRules()
  {
    $this->rules = [];
  }

  /**
   * Removes rule by its id.
   *
   * @param mixed $ruleId
   */
  public function removeRuleById($ruleId)
  {
    foreach ($this->rules as $ruleIndex => $rule) {
      if ($rule->id === $ruleId) {
        unset($this->rules[$ruleIndex]);

        return;
      }
    }
  }
}
