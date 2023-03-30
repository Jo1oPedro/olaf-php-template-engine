<?php $this->extend('layout') ?>
<?php $this->block('title', 'My Site Title') ?>
<h1>Simple Template Engine</h1>

<p>Hello, today's date is <?php echo $date; ?>.</p>
<p>This is a simple example page to get you started!</p>
<?php $this->block() ?>
    <?php $this->block(); ?>
        casCaTinha
    <?php $this->recursiveEndblock(function ($content) {
        return strtolower($content);
    }) ?>
<?php echo $this->endblock() ?>
<p>
<?php $this->block() ?>
	Enjoy!
<?php echo $this->endblock(function($content) {
	return strtoupper($content);
});
?></p>


<?php echo 'dale23' ?>

<?php echo $this['content']; ?>

<?php $this->component('dale123') ?>
    <?php $this->componentVariable('arroz', 123) ?>
    <?php $this->componentVariable('bala', 321) ?>
    <?php $this->componentVariable('maca', 213) ?>
<?php $this->endComponent('teste') ?>

<h3>Learn more</h3>
<p>Refer to the README file.</p>

<?php //file_get_contents('dale');

echo 'dale'?>

<?php ('$var = (20 - 5);'); echo ['HTTP_USER_AGENT']?>

<?php count(['dale']); ?>
