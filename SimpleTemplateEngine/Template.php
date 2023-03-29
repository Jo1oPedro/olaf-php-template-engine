<?php

/**
 * @author Duncan Cai 
 */

namespace SimpleTemplateEngine;

/**
 * Template class
 * 
 * Blocks in the template can be accessed by using this object as an array.
 * Allows access to shared environment variables as class variables with magic get and set.
 */
class Template implements \ArrayAccess
{

	/** @var string|null directory of the actual template */
	protected $templatePath;

	/** @var Environment enviroment that called this template */
	protected $environment;

	/** @var Block */
	protected $content;

	/** @var array */
	private $stack = array();

	/** @var array */
	protected $blocks = array();

	protected $extends = null;

	/** @var string[] array of functions that are not allowed to exists in a php file being executed */
	protected $notAllowedFunctions = ['exec', 'eval', 'system', 'shell_exec', 'passthru'];

	/** @var string[] array of globals that are not allowed to exists int a php file being executed */
	protected $notAllowedGlobals = ['$_SERVER', '$_SESSION', '$_COOKIE', '$_REQUEST'];


	/**
	 * Constructs a template from a file path
	 * If file path is null, constructs an empty template
	 * @param string $path the file complete path
	 */
	public function __construct($path = null) {
		$this->templatePath = $path;
		$this->environment = null;
		$this->content = new Block();
	}
	
	/**
	 * Creates a template within an environment
	 * @param Environment $environment
	 * @param string $path
	 * @return Template
	 */
	public static function withEnvironment(Environment $environment, $path) {
		if($path === null)
			$obj = new self(null);
		else
			$obj = new self($environment->getTemplatePath($path));
			//die(var_dump($obj));
		$obj->setEnvironment($environment);
		return $obj;
	}
	
	/**
	 * Allows this template to extend another template.
	 * A template can only extend one other template at a time however
	 * you can extend a template extending another template etc.
	 * 
	 * If a template extending another does not define a content block
	 * then the output of the extending template will become the content
	 * block of the extended template.
	 * 
	 * @param string $path
	 */
	public function extend($path) {
		if($path === null) {
			return;
		} else if($this->environment !== null) {
			if($this->templatePath == $this->environment->getTemplatePath($path))
				return;
			$this->extends = Template::withEnvironment($this->environment, $path);
		} else if($this->templatePath != $path) {
			$this->extends = new Template($path);
		}
	}
	
	/**
	 * Indicates the start of a block.
	 * If a value for the block is defined, it does not need to be closed with endblock
	 * @param string $name name of the block
	 * @param string $value optional value for the block
	 * @throws \LogicException
	 */
	public function block($name = null, $value = null) {
		
		if($value !== null) {
			if($name !== null) {
				$block = new Block($name);
				$block->setContent($value);
				$this->blocks[$name] = $block;
			} else
				throw new \LogicException(sprintf("You are assigning a value of %s to a block with no name!", $value));
			return;
		}
		
		if(!empty($this->stack)) {
			$content = ob_get_contents();
			foreach($this->stack as &$b)
				$b->append($content);
		}
		
		ob_start();
		$block = new Block($name);
		array_push($this->stack, $block);
	}
	
	/**
	 * Indicates the end of a block, and optionally accepts a filter to apply to the content of the actual block.
	 * Returns the block as a string because of the method __toString.
	 * @param \Closure|null $filter function to apply to block contents
	 * @return Block object
	 */
	public function endblock(\Closure $filter = null) {
		$content = ob_get_clean();
		//nested blocks
		foreach($this->stack as &$b)
			$b->append($content);
		$block = array_pop($this->stack);
		
		if($filter !== null) {
			$block->setContent($filter($block->getContent()));
		}
		
		if(($name = $block->getName()) != null)
			$this->blocks[$block->getName()] = $block;
		return $block;
	}

	/**
	 * Indicates the end of a block, and filter to apply to the content.
	 * As the name indicates it runs throught all blocks applying the filter to the content
	 * Returns the block as a string because of the magic method __toString.
	 * @param \Closure $filter function to apply to block contents
	 * @return Block object
	 */
	public function recursiveEndblock(\Closure $filter) {
		$content = ob_get_clean();
		//nested blocks
		foreach($this->stack as &$b)
			$b->append($filter($content));
		$block = array_pop($this->stack);

		if(($name = $block->getName()) != null)
			$this->blocks[$block->getName()] = $block;
		return $block;
	}

	/**
	 * Gets the blocks.
	 * @return array Block[]
	 */
	public function getBlocks() {
		if(!$this['content'])
			$this['content'] = $this->content;
		else
			$this['content'] = $this['content'] . $this->content;
		return $this->blocks;
	}

	/**
	 * Sets the blocks.
	 * @param array $blocks 
	 */
	public function setBlocks(array $blocks) {
		$this->blocks = $blocks;
	}

	/**
	 * Method responsible for security against closures being passed to views.
	 * @param array $variables
	 * @return array
	 */
	private function noClosureAllowed($variables) {
		return array_filter($variables, function ($variable) {
			return !($variable instanceof \Closure);
		});
	}

	/**
	 * Method responsible for security inside files.
	 * It allows to keep the content of any file being rendered inside the shape defined
	 * by the properties: notAllowedFunctions and notAllowedGlobals
	 * @param string $_file
	 * @return void
	 */
	private function onlyAllowedFunctions($_file) {
		file_put_contents($_file, str_replace(
			array_merge($this->notAllowedFunctions, $this->notAllowedGlobals),
			[],
			file_get_contents($_file)
		));
	}

	/**
	 * Renders a template and returns it as a string.
	 * @param string $templatePath
	 * @return string 
	 */
	public function render(array $variables = array()) {
		if($this->templatePath !== null) {
			$_file = $this->templatePath;
			
			if(!file_exists($_file))
					throw new \InvalidArgumentException(sprintf("Could not render.  The file %s could not be found", $_file));

			$variables = $this->noClosureAllowed($variables);
			$this->onlyAllowedFunctions($_file);
			extract($variables, EXTR_SKIP);

			ob_start();
			require($_file);
			$this->content->append(ob_get_clean());
		}
		
		//extending another template
		if($this->extends !== null) {
			$this->extends->setBlocks($this->getBlocks());
			$content = (string)$this->extends->render();
			return $content;
		}
		
		return (string)$this->content;
	}
	
	/**
	 * Sets template environment
	 * @param Environment $environment
	 */
	public function setEnvironment(Environment $environment) {
		$this->environment = $environment;
	}
	
	/**
	 * Magic isset
	 * @param string $id
	 * @return boolean 
	 */
	public function __isset($id) {
		return isset($this->environment->$id);
	}
	
	/**
	 * Magic getter
	 * @param string $id
	 * @return string
	 */
	public function __get($id) {
		return $this->environment->$id;
	}
	
	/**
	 * Magic setter
	 * @param string $id
	 * @param mixed $value 
	 */
	public function __set($id, $value) {
		$this->environment->$id = $value;
	}
	
	/**
	 * ArrayAccess offsetExists
	 * @param string $offset
	 * @return boolean 
	 */
	public function offsetExists($offset) {
		return isset($this->blocks[$offset]);
	}
	
	/**
	 * ArrayAccess offsetGet
	 * @param string $offset
	 * @return boolean|Block 
	 */
	public function offsetGet($offset) {
		if(isset($this->blocks[$offset]))
			return $this->blocks[$offset];
		else
			return false;
	}
	
	/**
	 * ArrayAccess offsetSet
	 * @param string $offset
	 * @param string-castable $value 
	 */
	public function offsetSet($offset, $value) {
		if(isset($this->blocks[$offset]))
			$this->blocks[$offset]->setContent((string)$value);
		else {
			$block = new Block($offset);
			$block->setContent((string)$value);
			$this->blocks[$offset] = $block;
		}
	}
	
	/**
	 * ArrayAccess offsetUnset
	 * @param string $offset 
	 */
	public function offsetUnset($offset) {
		unset($this->blocks[$offset]);
	}
	
}

?>
