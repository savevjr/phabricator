<?php

abstract class PhabricatorFerretEngine extends Phobject {

  abstract public function getApplicationName();
  abstract public function getScopeName();
  abstract public function newSearchEngine();

  public function getDefaultFunctionKey() {
    return 'all';
  }

  public function getObjectTypeRelevance() {
    return 1000;
  }

  public function getFieldForFunction($function) {
    $function = phutil_utf8_strtolower($function);

    $map = $this->getFunctionMap();
    if (!isset($map[$function])) {
      throw new PhutilSearchQueryCompilerSyntaxException(
        pht(
          'Unknown search function "%s". Supported functions are: %s.',
          $function,
          implode(', ', array_keys($map))));
    }

    return $map[$function]['field'];
  }

  protected function getFunctionMap() {
    return array(
      'all' => array(
        'field' => PhabricatorSearchDocumentFieldType::FIELD_ALL,
        'aliases' => array(
          'any',
        ),
      ),
      'title' => array(
        'field' => PhabricatorSearchDocumentFieldType::FIELD_TITLE,
        'aliases' => array(),
      ),
      'body' => array(
        'field' => PhabricatorSearchDocumentFieldType::FIELD_BODY,
        'aliases' => array(),
      ),
      'core' => array(
        'field' => PhabricatorSearchDocumentFieldType::FIELD_CORE,
        'aliases' => array(),
      ),
      'comment' => array(
        'field' => PhabricatorSearchDocumentFieldType::FIELD_COMMENT,
        'aliases' => array(
          'comments',
        ),
      ),
    );
  }

  public function newStemmer() {
    return new PhutilSearchStemmer();
  }

  public function newTermsCorpus($raw_corpus) {
    $term_corpus = strtr(
      $raw_corpus,
      array(
        '!' => ' ',
        '"' => ' ',
        '#' => ' ',
        '$' => ' ',
        '%' => ' ',
        '&' => ' ',
        '(' => ' ',
        ')' => ' ',
        '*' => ' ',
        '+' => ' ',
        ',' => ' ',
        '-' => ' ',
        '/' => ' ',
        ':' => ' ',
        ';' => ' ',
        '<' => ' ',
        '=' => ' ',
        '>' => ' ',
        '?' => ' ',
        '@' => ' ',
        '[' => ' ',
        '\\' => ' ',
        ']' => ' ',
        '^' => ' ',
        '`' => ' ',
        '{' => ' ',
        '|' => ' ',
        '}' => ' ',
        '~' => ' ',
        '.' => ' ',
        '_' => ' ',
        "\n" => ' ',
        "\r" => ' ',
        "\t" => ' ',
      ));

    // NOTE: Single quotes divide terms only if they're at a word boundary.
    // In contractions, like "whom'st've", the entire word is a single term.
    $term_corpus = preg_replace('/(^| )[\']+/', ' ', $term_corpus);
    $term_corpus = preg_replace('/[\']+( |$)/', ' ', $term_corpus);

    $term_corpus = preg_replace('/\s+/u', ' ', $term_corpus);
    $term_corpus = trim($term_corpus, ' ');

    if (strlen($term_corpus)) {
      $term_corpus = ' '.$term_corpus.' ';
    }

    return $term_corpus;
  }

/* -(  Schema  )------------------------------------------------------------- */

  public function getDocumentTableName() {
    $application = $this->getApplicationName();
    $scope = $this->getScopeName();

    return "{$application}_{$scope}_fdocument";
  }

  public function getDocumentSchemaColumns() {
    return array(
      'id' => 'auto',
      'objectPHID' => 'phid',
      'isClosed' => 'bool',
      'authorPHID' => 'phid?',
      'ownerPHID' => 'phid?',
      'epochCreated' => 'epoch',
      'epochModified' => 'epoch',
    );
  }

  public function getDocumentSchemaKeys() {
    return array(
      'PRIMARY' => array(
        'columns' => array('id'),
        'unique' => true,
      ),
      'key_object' => array(
        'columns' => array('objectPHID'),
        'unique' => true,
      ),
      'key_author' => array(
        'columns' => array('authorPHID'),
      ),
      'key_owner' => array(
        'columns' => array('ownerPHID'),
      ),
      'key_created' => array(
        'columns' => array('epochCreated'),
      ),
      'key_modified' => array(
        'columns' => array('epochModified'),
      ),
    );
  }

  public function getFieldTableName() {
    $application = $this->getApplicationName();
    $scope = $this->getScopeName();

    return "{$application}_{$scope}_ffield";
  }

  public function getFieldSchemaColumns() {
    return array(
      'id' => 'auto',
      'documentID' => 'uint32',
      'fieldKey' => 'text4',
      'rawCorpus' => 'sort',
      'termCorpus' => 'sort',
      'normalCorpus' => 'sort',
    );
  }

  public function getFieldSchemaKeys() {
    return array(
      'PRIMARY' => array(
        'columns' => array('id'),
        'unique' => true,
      ),
      'key_documentfield' => array(
        'columns' => array('documentID', 'fieldKey'),
        'unique' => true,
      ),
    );
  }

  public function getNgramsTableName() {
    $application = $this->getApplicationName();
    $scope = $this->getScopeName();

    return "{$application}_{$scope}_fngrams";
  }

  public function getNgramsSchemaColumns() {
    return array(
      'id' => 'auto',
      'documentID' => 'uint32',
      'ngram' => 'char3',
    );
  }

  public function getNgramsSchemaKeys() {
    return array(
      'PRIMARY' => array(
        'columns' => array('id'),
        'unique' => true,
      ),
      'key_ngram' => array(
        'columns' => array('ngram', 'documentID'),
      ),
      'key_object' => array(
        'columns' => array('documentID'),
      ),
    );
  }

  public function getCommonNgramsTableName() {
    $application = $this->getApplicationName();
    $scope = $this->getScopeName();

    return "{$application}_{$scope}_fngrams_common";
  }

  public function getCommonNgramsSchemaColumns() {
    return array(
      'id' => 'auto',
      'ngram' => 'char3',
      'needsCollection' => 'bool',
    );
  }

  public function getCommonNgramsSchemaKeys() {
    return array(
      'PRIMARY' => array(
        'columns' => array('id'),
        'unique' => true,
      ),
      'key_ngram' => array(
        'columns' => array('ngram'),
        'unique' => true,
      ),
      'key_collect' => array(
        'columns' => array('needsCollection'),
      ),
    );
  }

}
