<?php

/**
 * Abstract class for defining custom post types.  
 * 
 **/
abstract class CustomPostType{
	public 
		$name           = 'custom_post_type',
		$plural_name    = 'Custom Posts',
		$singular_name  = 'Custom Post',
		$add_new_item   = 'Add New Custom Post',
		$edit_item      = 'Edit Custom Post',
		$new_item       = 'New Custom Post',
		$public         = True,  # I dunno...leave it true
		$use_title      = True,  # Title field
		$use_editor     = True,  # WYSIWYG editor, post content field
		$use_revisions  = True,  # Revisions on post content and titles
		$use_thumbnails = False, # Featured images
		$use_order      = False, # Wordpress built-in order meta data
		$use_metabox    = False, # Enable if you have custom fields to display in admin
		$use_shortcode  = False, # Auto generate a shortcode for the post type
		                         # (see also objectsToHTML and toHTML methods)
		$taxonomies     = array('post_tag'),
		$built_in       = False,

		# Optional default ordering for generic shortcode if not specified by user.
		$default_orderby = null,
		$default_order   = null;
	
	
	/**
	 * Wrapper for get_posts function, that predefines post_type for this
	 * custom post type.  Any options valid in get_posts can be passed as an
	 * option array.  Returns an array of objects.
	 **/
	public function get_objects($options=array()){

		$defaults = array(
			'numberposts'   => -1,
			'orderby'       => 'title',
			'order'         => 'ASC',
			'post_type'     => $this->options('name'),
		);
		$options = array_merge($defaults, $options);
		$objects = get_posts($options);
		return $objects;
	}
	
	
	/**
	 * Similar to get_objects, but returns array of key values mapping post
	 * title to id if available, otherwise it defaults to id=>id.
	 **/
	public function get_objects_as_options($options=array()){
		$objects = $this->get_objects($options);
		$opt     = array();
		foreach($objects as $o){
			switch(True){
				case $this->options('use_title'):
					$opt[$o->post_title] = $o->ID;
					break;
				default:
					$opt[$o->ID] = $o->ID;
					break;
			}
		}
		return $opt;
	}
	
	
	/**
	 * Return the instances values defined by $key.
	 **/
	public function options($key){
		$vars = get_object_vars($this);
		return $vars[$key];
	}
	
	
	/**
	 * Additional fields on a custom post type may be defined by overriding this
	 * method on an descendant object.
	 **/
	public function fields(){
		return array();
	}
	
	
	/**
	 * Using instance variables defined, returns an array defining what this
	 * custom post type supports.
	 **/
	public function supports(){
		#Default support array
		$supports = array();
		if ($this->options('use_title')){
			$supports[] = 'title';
		}
		if ($this->options('use_order')){
			$supports[] = 'page-attributes';
		}
		if ($this->options('use_thumbnails')){
			$supports[] = 'thumbnail';
		}
		if ($this->options('use_editor')){
			$supports[] = 'editor';
		}
		if ($this->options('use_revisions')){
			$supports[] = 'revisions';
		}
		return $supports;
	}
	
	
	/**
	 * Creates labels array, defining names for admin panel.
	 **/
	public function labels(){
		return array(
			'name'          => __($this->options('plural_name')),
			'singular_name' => __($this->options('singular_name')),
			'add_new_item'  => __($this->options('add_new_item')),
			'edit_item'     => __($this->options('edit_item')),
			'new_item'      => __($this->options('new_item')),
		);
	}
	
	
	/**
	 * Creates metabox array for custom post type. Override method in
	 * descendants to add or modify metaboxes.
	 **/
	public function metabox(){
		if ($this->options('use_metabox')){
			return array(
				'id'       => $this->options('name').'_metabox',
				'title'    => __($this->options('singular_name').' Fields'),
				'page'     => $this->options('name'),
				'context'  => 'normal',
				'priority' => 'high',
				'fields'   => $this->fields(),
			);
		}
		return null;
	}
	
	
	/**
	 * Registers metaboxes defined for custom post type.
	 **/
	public function register_metaboxes(){
		if ($this->options('use_metabox')){
			$metabox = $this->metabox();
			add_meta_box(
				$metabox['id'],
				$metabox['title'],
				'show_meta_boxes',
				$metabox['page'],
				$metabox['context'],
				$metabox['priority']
			);
		}
	}
	
	
	/**
	 * Registers the custom post type and any other ancillary actions that are
	 * required for the post to function properly.
	 **/
	public function register(){
		$registration = array(
			'labels'     => $this->labels(),
			'supports'   => $this->supports(),
			'public'     => $this->options('public'),
			'taxonomies' => $this->options('taxonomies'),
			'_builtin'   => $this->options('built_in')
		);
		
		if ($this->options('use_order')){
			$registration = array_merge($registration, array('hierarchical' => True,));
		}
		
		register_post_type($this->options('name'), $registration);
		
		if ($this->options('use_shortcode')){
			add_shortcode($this->options('name').'-list', array($this, 'shortcode'));
		}
	}
	
	
	/**
	 * Shortcode for this custom post type.  Can be overridden for descendants.
	 * Defaults to just outputting a list of objects outputted as defined by
	 * toHTML method.
	 **/
	public function shortcode($attr){
		$default = array(
			'type' => $this->options('name'),
		);
		if (is_array($attr)){
			$attr = array_merge($default, $attr);
		}else{
			$attr = $default;
		}
		return sc_object_list($attr);
	}
	
	
	/**
	 * Handles output for a list of objects, can be overridden for descendants.
	 * If you want to override how a list of objects are outputted, override
	 * this, if you just want to override how a single object is outputted, see
	 * the toHTML method.
	 **/
	public function objectsToHTML($objects, $css_classes){
		if (count($objects) < 1){ return '';}
		
		$class = get_custom_post_type($objects[0]->post_type);
		$class = new $class;
		
		ob_start();
		?>
		<ul class="<?php if($css_classes):?><?=$css_classes?><?php else:?><?=$class->options('name')?>-list<?php endif;?>">
			<?php foreach($objects as $o):?>
			<li>
				<?=$class->toHTML($o)?>
			</li>
			<?php endforeach;?>
		</ul>
		<?php
		$html = ob_get_clean();
		return $html;
	}
	
	
	/**
	 * Outputs this item in HTML.  Can be overridden for descendants.
	 **/
	public function toHTML($object){
		$html = '<a href="'.get_permalink($object->ID).'">'.$object->post_title.'</a>';
		return $html;
	}
}


class ResourceLink extends CustomPostType{
	public
		$name           = 'resourcelink',
		$plural_name    = 'Resource Links',
		$singular_name  = 'Resource Link',
		$add_new_item   = 'Add New Resource Link',
		$edit_item      = 'Edit Resource Link',
		$new_item       = 'New Resource Link',
		$use_title      = True,
		$use_editor     = False,
		$use_shortcode  = True,
		$use_metabox    = True,
		$taxonomies     = array('post_tag', 'pg_sections');
	
	public static function get_page_dropdown() {
		$args = array(
			'numberposts' 	=> -1,
			'post_type'		=> 'page',
			'post_status' 	=> 'publish',
		);
		$pages = get_posts($args);
		
		$page_options = array();
		foreach ($pages as $page) {
			$page_options[$page->post_title] = $page->ID;
		}
		
		return $page_options;
	}
	
	public function fields(){
		$fields   = parent::fields();
		$fields[] = array(
			'name' => __('URL'),
			'desc' => __('Associate this link with a URL.  This will take precedence over any uploaded file or page choice.  If the URL begins with a hash tag # (designating an inner-page anchor), an Existing Page should also be specified.'),
			'id'   => $this->options('name').'_url',
			'type' => 'text',
		);
		$fields[] = array(
			'name'    => __('Existing Page'),
			'desc'    => __('Associate this link with an already existing page.  An inner-page link can be specified by choosing a page below and typing an anchor link in the URL field (i.e. #some-section)'),
			'id'      => $this->options('name').'_page',
			'type' => 'select',
			'options' =>  $this->get_page_dropdown(),
		);
		$fields[] = array(
			'name'    => __('File'),
			'desc'    => __('Associate this link with an already existing file.  Make sure URL and Existing Page are left blank if you want to use a file.'),
			'id'      => $this->options('name').'_file',
			'type'    => 'file',
		);
		$fields[] = array(
			'name'    => __('Is a Document'),
			'desc'    => __('Specifies whether the Resource Link is a downloadable document (and appears on the Documents and Forms page.)'),
			'id'      => $this->options('name').'_is_doc',
			'type'    => 'checkbox',
		);
		return $fields;
	}
	
	
	static function get_document_application($form){
		return mimetype_to_application(self::get_mimetype($form));
	}
	
	
	static function get_mimetype($form){
		if (is_numeric($form)){
			$form = get_post($form);
		}
		
		$prefix   = post_type($form);
		$document = get_post(get_post_meta($form->ID, $prefix.'_file', True));
		
		$url = get_post_meta($form->ID, $prefix.'_url', True);
		$page = get_post_meta($form->ID, $prefix.'_page', True);
		
		if ($url) {
			$link_type = (substr($url, 0, 1) == '#' && $page) ? 'page' : 'text/html';
		}
		elseif ($page) {
			$link_type = 'page';
		}
		elseif ($document) {
			$link_type = $document->post_mime_type;
		}
		
		return $link_type;
	}
	
	
	static function get_title($form){
		if (is_numeric($form)){
			$form = get_post($form);
		}
		
		$prefix = post_type($form);
		
		return $form->post_title;
	}
	
	static function get_url($form){
		if (is_numeric($form)){
			$form = get_post($form);
		}
		
		$prefix = post_type($form);
		
		// URLS take precedence over any other fields.
		// Pages take precedence over files.
		$url = get_post_meta($form->ID, $prefix.'_url', True);
		$file = wp_get_attachment_url(get_post_meta($form->ID, $prefix.'_file', True));
		$page = get_post_meta($form->ID, $prefix.'_page', True);
		
		if (!$url and !$file and !$page){
			return '#';
		}
		
		if ($url) {
			if (substr($url, 0, 1) == '#' && $page) {
				return get_permalink($page).$url;
			}
			else {
				return $url;
			}
		}
		elseif ($page) {
			return get_permalink($page);
		}
		else {
			return $file;
		}
	}
	/*
	static function get_link_type($form) {		
		if (get_post_meta($form->ID, 'resourcelink_is_doc', TRUE)) {
			return 'document';
		}
		elseif ( (get_post_meta($form->ID, 'resourcelink_page', TRUE) ) || ( substr(get_post_meta($form->ID, 'resourcelink_url', TRUE), 0, 1) == '#') ) {
			return 'internal';
		}
		else {
			return 'external';
		}
	}
	*/
	
	/**
	 * Handles output for a list of objects, can be overridden for descendants.
	 * If you want to override how a list of objects are outputted, override
	 * this, if you just want to override how a single object is outputted, see
	 * the toHTML method.
	 **/
	public function objectsToHTML($objects, $css_classes){
		if (count($objects) < 1){ return '';}
		
		$class_name = get_custom_post_type($objects[0]->post_type);
		$class      = new $class_name;
		
		ob_start();
		?>
		<ul class="nobullet <?php if($css_classes):?><?=$css_classes?><?php else:?><?=$class->options('name')?>-list<?php endif;?>">
			<?php foreach($objects as $o):?>
			<li class="resource-link <?=$class_name::get_document_application($o)?>">
				<?=$class->toHTML($o)?>
			</li>
			<?php endforeach;?>
		</ul>
		<?php
		$html = ob_get_clean();
		return $html;
	}
	
	
	/**
	 * Outputs this item in HTML.  Can be overridden for descendants.
	 **/
	public function toHTML($object){
		$title    = ResourceLink::get_title($object);
		$url      = ResourceLink::get_url($object);
		$linktype = ResourceLink::get_document_application($object);
		$html = "<a href='{$url}'>{$title}</a>";
		return $html;
	}
}


class Video extends CustomPostType{
	public 
		$name           = 'video',
		$plural_name    = 'Videos',
		$singular_name  = 'Video',
		$add_new_item   = 'Add New Video',
		$edit_item      = 'Edit Video',
		$new_item       = 'New Video',
		$public         = True,
		$use_editor     = False,
		$use_thumbnails = True,
		$use_order      = True,
		$use_title      = True,
		$use_metabox    = True;
	
	public function get_player_html($video){
		return sc_video(array('video' => $video));
	}
	
	public function metabox(){
		$metabox = parent::metabox();
		
		$metabox['title']   = 'Videos on Media Page';
		$metabox['helptxt'] = 'Video icon will be resized to width 210px, height 118px.';
		return $metabox;
	}
	
	public function fields(){
		$prefix = $this->options('name').'_';
		return array(
			array(
				'name' => 'URL',
				'desc' => 'YouTube URL pointing to video.<br>  Example: http://www.youtube.com/watch?v=IrSeMg7iPbM',
				'id'   => $prefix.'url',
				'type' => 'text',
				'std'  => ''
			),
			array(
				'name' => 'Video Description',
				'desc' => 'Short description of the video.',
				'id'   => $prefix.'description',
				'type' => 'textarea',
				'std'  => ''
			),
			array(
				'name' => 'Shortcode',
				'desc' => 'To include this video in other posts, use the following shortcode:',
				'id'   => 'video_shortcode',
				'type' => 'shortcode',
				'value' => '[video name="TITLE"]',
			),
		);
	}
}


class Publication extends CustomPostType{
	public 
		$name           = 'publication',
		$plural_name    = 'Publications',
		$singular_name  = 'Publication',
		$add_new_item   = 'Add New Publication',
		$edit_item      = 'Edit Publication',
		$new_item       = 'New Publication',
		$public         = True,
		$use_editor     = False,
		$use_thumbnails = True,
		$use_order      = True,
		$use_title      = True,
		$use_metabox    = True;
	
	public function toHTML($pub){
		return sc_publication(array('pub' => $pub));
	}
	
	public function metabox(){
		$metabox = parent::metabox();
		
		$metabox['title']   = 'Publications on Media Page';
		$metabox['helptxt'] = 'Publication cover icon will be resized to width 153px, height 198px.';
		return $metabox;
	}
	
	public function fields(){
		$prefix = $this->options('name').'_';
		return array(
			array(
				'name'  => 'Publication URL',
				'desc' => 'Example: <span style="font-family:monospace;font-weight:bold;color:#21759B;">http://publications.smca.ucf.edu/admissions/viewbook.html</span>',
				'id'   => $prefix.'url',
				'type' => 'text',
				'std'  => '',
			),
			array(
				'name' => 'Shortcode',
				'desc' => 'To include this publication in other posts, use the following shortcode: <input disabled="disabled" type="text" value="[publication name=]" />',
				'id'   => 'publication_shortcode',
				'type' => 'help',
				'value' => '[publication name="TITLE"]',
			),
		);
	}
}

class Page extends CustomPostType {
	public
		$name           = 'page',
		$plural_name    = 'Pages',
		$singular_name  = 'Page',
		$add_new_item   = 'Add New Page',
		$edit_item      = 'Edit Page',
		$new_item       = 'New Page',
		$public         = True,
		$use_editor     = True,
		$use_thumbnails = False,
		$use_order      = True,
		$use_title      = True,
		$use_metabox    = True,
		$built_in       = True;

	public function fields() {
		$prefix = $this->options('name').'_';
		return array(
			array(
				'name' => 'Subheader',
				'desc' => '(Optional) Add text that will go directly before the page title.',
				'id'   => $prefix.'subheader',
				'type' => 'textarea',
			),
			array(
				'name' => 'Post Search Taxonomy_Term',
				'desc' => '(Optional) By default, pages with a two-column layout will pull post search results by the taxonomy term that matches the current page slug.  To pull posts from a different taxonomy term, type the term slug (e.g. "some-term-name") here.',
				'id'   => $prefix.'taxonomy_term',
				'type' => 'text',
			),
			array(
				'name' => 'Stylesheet',
				'desc' => '',
				'id' => $prefix.'stylesheet',
				'type' => 'file',
			),
		);
	}
}

/**
 * Describes a staff member
 *
 * @author Chris Conover
 **/
class Person extends CustomPostType
{
	/*
	The following query will pre-populate the person_orderby_name
	meta field with a guess of the last name extracted from the post title.
	
	>>>BE SURE TO REPLACE wp_<number>_... WITH THE APPROPRIATE SITE ID<<<
	
	INSERT INTO wp_29_postmeta(post_id, meta_key, meta_value) 
	(	SELECT	id AS post_id, 
						'person_orderby_name' AS meta_key, 
						REVERSE(SUBSTR(REVERSE(post_title), 1, LOCATE(' ', REVERSE(post_title)))) AS meta_value
		FROM		wp_29_posts AS posts
		WHERE		post_type = 'person' AND
						(	SELECT meta_id 
							FROM wp_29_postmeta 
							WHERE post_id = posts.id AND
										meta_key = 'person_orderby_name') IS NULL)
	*/

	public
		$name           = 'person',
		$plural_name    = 'People',
		$singular_name  = 'Person',
		$add_new_item   = 'Add Person',
		$edit_item      = 'Edit Person',
		$new_item       = 'New Person',
		$public         = True,
		$use_shortcode  = True,
		$use_metabox    = True,
		$use_thumbnails = True,
		$use_order      = True,
		$taxonomies     = array('org_groups', 'category');

		public function fields(){
			$fields = array(
				array(
					'name'    => __('Title Prefix'),
					'desc'    => '',
					'id'      => $this->options('name').'_title_prefix',
					'type'    => 'text',
				),
				array(
					'name'    => __('Title Suffix'),
					'desc'    => __('Be sure to include leading comma or space if neccessary.'),
					'id'      => $this->options('name').'_title_suffix',
					'type'    => 'text',
				),
				array(
					'name'    => __('Job Title'),
					'desc'    => __(''),
					'id'      => $this->options('name').'_jobtitle',
					'type'    => 'text',
				),
				array(
					'name'    => __('Phone'),
					'desc'    => __('Separate multiple entries with commas.'),
					'id'      => $this->options('name').'_phones',
					'type'    => 'text',
				),/*
				array(
					'name'    => __('Email'),
					'desc'    => __(''),
					'id'      => $this->options('name').'_email',
					'type'    => 'text',
				),*/
				array(
					'name'    => __('Order By Name'),
					'desc'    => __('Name used for sorting. Leaving this field blank may lead to an unexpected sort order.'),
					'id'      => $this->options('name').'_orderby_name',
					'type'    => 'text',
				),
			);
			return $fields;
		}

	public function get_objects($options=array()){
		$options['order']    = 'DESC';
		$options['orderby']  = 'meta_value';
		$options['meta_key'] = 'person_orderby_name';
		return parent::get_objects($options);
	}

	public function shortcode($attr=array()){
		$attr['type']		= 'person';
		$attr['order']		= 'ASC';
		$attr['orderby']	= 'post_date';
		return parent::shortcode($attr);
	}

	public static function get_name($person) {
		$prefix = get_post_meta($person->ID, 'person_title_prefix', True);
		$suffix = get_post_meta($person->ID, 'person_title_suffix', True);
		$name = $person->post_title;
		return $prefix.' '.$name.' '.$suffix;
	}

	public static function get_phones($person) {
		$phones = get_post_meta($person->ID, 'person_phones', True);
		return ($phones != '') ? explode(',', $phones) : array();
	}

	public function objectsToHTML($people, $css_classes) {
		ob_start();?>
		<div class="row">
			<div class="span12">
				<table class="table table-striped">
					<thead>
						<tr>
							<th scope="col" class="name">Name</th>
							<th scope="col" class="job_title">Title</th>
							<th scope="col" class="phones">Phone</th>
						</tr>
					</thead>
					<tbody>
				<?
				foreach($people as $person) { 
					$link = ($person->post_content == '') ? False : True; ?>
						<tr>
							<td class="name span6">
								<?if($link) {?><a href="<?=get_permalink($person->ID)?>"><?}?>
									<?=$this->get_name($person)?>
								<?if($link) {?></a><?}?>
							</td>
							<td class="job_title span4">
								<?if($link) {?><a href="<?=get_permalink($person->ID)?>"><?}?>
								<?=get_post_meta($person->ID, 'person_jobtitle', True)?>
								<?if($link) {?></a><?}?>
							</td> 
							<td class="phones span2"><?php if(($link) && ($this->get_phones($person))) {?><a href="<?=get_permalink($person->ID)?>">
								<?php } if($this->get_phones($person)) {?>
									<ul class="unstyled"><?php foreach($this->get_phones($person) as $phone) { ?><li><?=$phone?></li><?php } ?></ul>
								<?php } if(($link) && ($this->get_phones($person))) {?></a><?php }?></td>
						</tr>
				<? } ?>
				</tbody>
			</table> 
		</div>
	</div><?
	return ob_get_clean();
	}
} // END class 

class Post extends CustomPostType {
	public
		$name           = 'post',
		$plural_name    = 'Posts',
		$singular_name  = 'Post',
		$add_new_item   = 'Add New Post',
		$edit_item      = 'Edit Post',
		$new_item       = 'New Post',
		$public         = True,
		$use_editor     = True,
		$use_thumbnails = False,
		$use_order      = True,
		$use_title      = True,
		$use_metabox    = True,
		$taxonomies     = array('post_tag', 'category'),
		$built_in       = True;

	public function fields() {
		$prefix = $this->options('name').'_';
		return array(
				array(
					'name' => 'Stylesheet',
					'desc' => '',
					'id' => $prefix.'stylesheet',
					'type' => 'file',
				),
		);
	}
}
?>