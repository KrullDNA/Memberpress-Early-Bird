<?php
/**
 * Early Bird Pricing Elementor widget.
 *
 * Pulls every live value from KDNA_Early_Bird_Engine. The widget never
 * computes offer state itself, so the front end always sees exactly what
 * MemberPress would see at the same moment in time.
 *
 * Atomic markup notes:
 *   - has_widget_inner_wrapper() returns false when the Elementor
 *     e_optimized_markup experiment is active.
 *   - Render emits a single wrapper div with kdna- classes only.
 *   - Style selectors never reference .elementor-widget-container.
 *   - get_style_depends() and get_script_depends() let Elementor enqueue
 *     the widget assets only when the widget is on the page.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
	return;
}

class KDNA_Early_Bird_Widget extends \Elementor\Widget_Base {

	public function get_name() {
		return 'kdna-early-bird-pricing';
	}

	public function get_title() {
		return __( 'Early Bird Pricing', 'kdna-early-bird' );
	}

	public function get_icon() {
		return 'eicon-price-list';
	}

	public function get_categories() {
		return array( 'general' );
	}

	public function get_keywords() {
		return array( 'memberpress', 'pricing', 'early bird', 'offer', 'kdna', 'countdown' );
	}

	public function get_style_depends() {
		return array( 'kdna-early-bird-widget' );
	}

	public function get_script_depends() {
		return array( 'kdna-early-bird-widget' );
	}

	/**
	 * Atomic Elementor requirement. Return false when the optimised
	 * markup experiment is active so that Elementor does not wrap our
	 * single div in another container.
	 */
	public function has_widget_inner_wrapper(): bool {
		if (
			class_exists( '\Elementor\Plugin' )
			&& isset( \Elementor\Plugin::$instance->experiments )
			&& is_object( \Elementor\Plugin::$instance->experiments )
			&& method_exists( \Elementor\Plugin::$instance->experiments, 'is_feature_active' )
		) {
			if ( \Elementor\Plugin::$instance->experiments->is_feature_active( 'e_optimized_markup' ) ) {
				return false;
			}
		}
		return true;
	}

	protected function register_controls() {
		$this->register_content_controls();
		$this->register_style_controls();
	}

	private function register_content_controls() {

		// Source.
		$this->start_controls_section(
			'kdna_source_section',
			array(
				'label' => __( 'Source', 'kdna-early-bird' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'rule_id',
			array(
				'label'       => __( 'Rule', 'kdna-early-bird' ),
				'type'        => \Elementor\Controls_Manager::SELECT,
				'options'     => $this->get_rule_options(),
				'default'     => '',
				'description' => __( 'The Early Bird rule this widget belongs to.', 'kdna-early-bird' ),
			)
		);

		$this->add_control(
			'membership_id',
			array(
				'label'       => __( 'Membership', 'kdna-early-bird' ),
				'type'        => \Elementor\Controls_Manager::SELECT,
				'options'     => $this->get_membership_options(),
				'default'     => '',
				'description' => __( 'Pick a MemberPress membership that is listed in the selected rule.', 'kdna-early-bird' ),
			)
		);

		$this->end_controls_section();

		// Elements.
		$this->start_controls_section(
			'kdna_elements_section',
			array(
				'label' => __( 'Display Elements', 'kdna-early-bird' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'show_active_label',
			array(
				'label'        => __( 'Show active label', 'kdna-early-bird' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Yes', 'kdna-early-bird' ),
				'label_off'    => __( 'No', 'kdna-early-bird' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'show_current_price',
			array(
				'label'        => __( 'Show current price', 'kdna-early-bird' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Yes', 'kdna-early-bird' ),
				'label_off'    => __( 'No', 'kdna-early-bird' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'show_full_price',
			array(
				'label'        => __( 'Show full price struck through', 'kdna-early-bird' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Yes', 'kdna-early-bird' ),
				'label_off'    => __( 'No', 'kdna-early-bird' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'show_spots',
			array(
				'label'        => __( 'Show spots remaining', 'kdna-early-bird' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Yes', 'kdna-early-bird' ),
				'label_off'    => __( 'No', 'kdna-early-bird' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'show_countdown',
			array(
				'label'        => __( 'Show time remaining', 'kdna-early-bird' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Yes', 'kdna-early-bird' ),
				'label_off'    => __( 'No', 'kdna-early-bird' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'countdown_mode',
			array(
				'label'     => __( 'Time remaining format', 'kdna-early-bird' ),
				'type'      => \Elementor\Controls_Manager::SELECT,
				'options'   => array(
					'days'      => __( 'Days remaining, plain text', 'kdna-early-bird' ),
					'countdown' => __( 'Live countdown, days hours minutes seconds', 'kdna-early-bird' ),
				),
				'default'   => 'days',
				'condition' => array( 'show_countdown' => 'yes' ),
			)
		);

		$this->add_control(
			'show_button',
			array(
				'label'        => __( 'Show button', 'kdna-early-bird' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Yes', 'kdna-early-bird' ),
				'label_off'    => __( 'No', 'kdna-early-bird' ),
				'return_value' => 'yes',
				'default'      => 'no',
			)
		);

		$this->add_control(
			'hide_when_ended',
			array(
				'label'        => __( 'Hide widget when offer has ended', 'kdna-early-bird' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Yes', 'kdna-early-bird' ),
				'label_off'    => __( 'No', 'kdna-early-bird' ),
				'return_value' => 'yes',
				'default'      => 'no',
				'description'  => __( 'When on, the widget renders nothing on the front end once the offer has ended.', 'kdna-early-bird' ),
			)
		);

		$this->end_controls_section();

		// Active state text.
		$this->start_controls_section(
			'kdna_active_text_section',
			array(
				'label' => __( 'Active State Text', 'kdna-early-bird' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'active_label_text',
			array(
				'label'   => __( 'Active label', 'kdna-early-bird' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'Early Bird Offer', 'kdna-early-bird' ),
			)
		);

		$this->add_control(
			'spots_template',
			array(
				'label'       => __( 'Spots remaining text', 'kdna-early-bird' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => __( '%d spots left', 'kdna-early-bird' ),
				'description' => __( 'Use %d as a placeholder for the number of spots remaining.', 'kdna-early-bird' ),
			)
		);

		$this->add_control(
			'days_template',
			array(
				'label'       => __( 'Days remaining text', 'kdna-early-bird' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => __( '%d days left', 'kdna-early-bird' ),
				'description' => __( 'Used when the time remaining format is set to plain days. Use %d for the number.', 'kdna-early-bird' ),
				'condition'   => array(
					'show_countdown' => 'yes',
					'countdown_mode' => 'days',
				),
			)
		);

		$this->add_control(
			'countdown_label_days',
			array(
				'label'     => __( 'Countdown days label', 'kdna-early-bird' ),
				'type'      => \Elementor\Controls_Manager::TEXT,
				'default'   => __( 'd', 'kdna-early-bird' ),
				'condition' => array(
					'show_countdown' => 'yes',
					'countdown_mode' => 'countdown',
				),
			)
		);

		$this->add_control(
			'countdown_label_hours',
			array(
				'label'     => __( 'Countdown hours label', 'kdna-early-bird' ),
				'type'      => \Elementor\Controls_Manager::TEXT,
				'default'   => __( 'h', 'kdna-early-bird' ),
				'condition' => array(
					'show_countdown' => 'yes',
					'countdown_mode' => 'countdown',
				),
			)
		);

		$this->add_control(
			'countdown_label_minutes',
			array(
				'label'     => __( 'Countdown minutes label', 'kdna-early-bird' ),
				'type'      => \Elementor\Controls_Manager::TEXT,
				'default'   => __( 'm', 'kdna-early-bird' ),
				'condition' => array(
					'show_countdown' => 'yes',
					'countdown_mode' => 'countdown',
				),
			)
		);

		$this->add_control(
			'countdown_label_seconds',
			array(
				'label'     => __( 'Countdown seconds label', 'kdna-early-bird' ),
				'type'      => \Elementor\Controls_Manager::TEXT,
				'default'   => __( 's', 'kdna-early-bird' ),
				'condition' => array(
					'show_countdown' => 'yes',
					'countdown_mode' => 'countdown',
				),
			)
		);

		$this->end_controls_section();

		// Ended state text.
		$this->start_controls_section(
			'kdna_ended_text_section',
			array(
				'label' => __( 'Ended State Text', 'kdna-early-bird' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'ended_label_text',
			array(
				'label'   => __( 'Ended label', 'kdna-early-bird' ),
				'type'    => \Elementor\Controls_Manager::TEXTAREA,
				'default' => __( 'This early bird offer has ended.', 'kdna-early-bird' ),
				'rows'    => 2,
			)
		);

		$this->end_controls_section();

		// Button.
		$this->start_controls_section(
			'kdna_button_section',
			array(
				'label'     => __( 'Button', 'kdna-early-bird' ),
				'tab'       => \Elementor\Controls_Manager::TAB_CONTENT,
				'condition' => array( 'show_button' => 'yes' ),
			)
		);

		$this->add_control(
			'button_text',
			array(
				'label'   => __( 'Button text', 'kdna-early-bird' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'Join now', 'kdna-early-bird' ),
			)
		);

		$this->add_control(
			'button_link',
			array(
				'label'         => __( 'Button URL', 'kdna-early-bird' ),
				'type'          => \Elementor\Controls_Manager::URL,
				'default'       => array(
					'url'         => '',
					'is_external' => false,
					'nofollow'    => false,
				),
				'placeholder'   => __( 'https://your-site.com/register', 'kdna-early-bird' ),
			)
		);

		$this->add_control(
			'button_icon',
			array(
				'label'            => __( 'Button icon', 'kdna-early-bird' ),
				'type'             => \Elementor\Controls_Manager::ICONS,
				'fa4compatibility' => 'icon',
			)
		);

		$this->add_control(
			'button_icon_position',
			array(
				'label'   => __( 'Icon position', 'kdna-early-bird' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'options' => array(
					'before' => __( 'Before text', 'kdna-early-bird' ),
					'after'  => __( 'After text', 'kdna-early-bird' ),
				),
				'default' => 'after',
			)
		);

		$this->end_controls_section();
	}

	private function register_style_controls() {
		$this->register_layout_style();
		$this->register_active_label_style();
		$this->register_current_price_style();
		$this->register_full_price_style();
		$this->register_spots_style();
		$this->register_countdown_style();
		$this->register_button_style();
		$this->register_ended_label_style();
	}

	private function register_layout_style() {
		$this->start_controls_section(
			'kdna_layout_style',
			array(
				'label' => __( 'Layout', 'kdna-early-bird' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_responsive_control(
			'layout_align',
			array(
				'label'     => __( 'Alignment', 'kdna-early-bird' ),
				'type'      => \Elementor\Controls_Manager::CHOOSE,
				'options'   => array(
					'flex-start' => array(
						'title' => __( 'Left', 'kdna-early-bird' ),
						'icon'  => 'eicon-text-align-left',
					),
					'center'     => array(
						'title' => __( 'Centre', 'kdna-early-bird' ),
						'icon'  => 'eicon-text-align-center',
					),
					'flex-end'   => array(
						'title' => __( 'Right', 'kdna-early-bird' ),
						'icon'  => 'eicon-text-align-right',
					),
				),
				'default'   => 'flex-start',
				'selectors' => array(
					'{{WRAPPER}} .kdna-early-bird-widget' => 'align-items: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'layout_gap',
			array(
				'label'      => __( 'Gap between elements', 'kdna-early-bird' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem' ),
				'range'      => array(
					'px' => array( 'min' => 0, 'max' => 80, 'step' => 1 ),
				),
				'default'    => array( 'unit' => 'px', 'size' => 12 ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-early-bird-widget' => 'gap: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'prices_gap',
			array(
				'label'      => __( 'Gap between prices', 'kdna-early-bird' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem' ),
				'range'      => array(
					'px' => array( 'min' => 0, 'max' => 40, 'step' => 1 ),
				),
				'default'    => array( 'unit' => 'px', 'size' => 8 ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-early-bird-prices' => 'gap: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();
	}

	private function register_active_label_style() {
		$this->start_controls_section(
			'kdna_active_label_style',
			array(
				'label'     => __( 'Active Label', 'kdna-early-bird' ),
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => array( 'show_active_label' => 'yes' ),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'active_label_typography',
				'selector' => '{{WRAPPER}} .kdna-early-bird-active-label',
			)
		);

		$this->add_control(
			'active_label_color',
			array(
				'label'     => __( 'Colour', 'kdna-early-bird' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-early-bird-active-label' => 'color: {{VALUE}};',
				),
			)
		);

		$this->end_controls_section();
	}

	private function register_current_price_style() {
		$this->start_controls_section(
			'kdna_current_price_style',
			array(
				'label'     => __( 'Current Price', 'kdna-early-bird' ),
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => array( 'show_current_price' => 'yes' ),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'current_price_typography',
				'selector' => '{{WRAPPER}} .kdna-early-bird-current-price',
			)
		);

		$this->add_control(
			'current_price_color',
			array(
				'label'     => __( 'Colour', 'kdna-early-bird' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-early-bird-current-price' => 'color: {{VALUE}};',
				),
			)
		);

		$this->end_controls_section();
	}

	private function register_full_price_style() {
		$this->start_controls_section(
			'kdna_full_price_style',
			array(
				'label'     => __( 'Full Price', 'kdna-early-bird' ),
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => array( 'show_full_price' => 'yes' ),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'full_price_typography',
				'selector' => '{{WRAPPER}} .kdna-early-bird-full-price',
			)
		);

		$this->add_control(
			'full_price_color',
			array(
				'label'     => __( 'Colour', 'kdna-early-bird' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-early-bird-full-price' => 'color: {{VALUE}};',
				),
			)
		);

		$this->end_controls_section();
	}

	private function register_spots_style() {
		$this->start_controls_section(
			'kdna_spots_style',
			array(
				'label'     => __( 'Spots Badge', 'kdna-early-bird' ),
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => array( 'show_spots' => 'yes' ),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'spots_typography',
				'selector' => '{{WRAPPER}} .kdna-early-bird-spots-badge',
			)
		);

		$this->add_control(
			'spots_text_color',
			array(
				'label'     => __( 'Text colour', 'kdna-early-bird' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-early-bird-spots-badge' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'spots_background',
			array(
				'label'     => __( 'Background', 'kdna-early-bird' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-early-bird-spots-badge' => 'background-color: {{VALUE}};',
				),
				'default'   => '#fff2c5',
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'     => 'spots_border',
				'selector' => '{{WRAPPER}} .kdna-early-bird-spots-badge',
			)
		);

		$this->add_responsive_control(
			'spots_border_radius',
			array(
				'label'      => __( 'Border radius', 'kdna-early-bird' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em' ),
				'default'    => array(
					'top' => 999, 'right' => 999, 'bottom' => 999, 'left' => 999, 'unit' => 'px',
				),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-early-bird-spots-badge' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'spots_padding',
			array(
				'label'      => __( 'Padding', 'kdna-early-bird' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', 'rem' ),
				'default'    => array(
					'top' => 4, 'right' => 12, 'bottom' => 4, 'left' => 12, 'unit' => 'px',
				),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-early-bird-spots-badge' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();
	}

	private function register_countdown_style() {
		$this->start_controls_section(
			'kdna_countdown_style',
			array(
				'label'     => __( 'Countdown', 'kdna-early-bird' ),
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => array( 'show_countdown' => 'yes' ),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'      => 'countdown_typography',
				'selector'  => '{{WRAPPER}} .kdna-early-bird-countdown, {{WRAPPER}} .kdna-early-bird-countdown-value',
			)
		);

		$this->add_control(
			'countdown_color',
			array(
				'label'     => __( 'Colour', 'kdna-early-bird' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-early-bird-countdown' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'countdown_unit_label_heading',
			array(
				'label'     => __( 'Unit labels (d h m s)', 'kdna-early-bird' ),
				'type'      => \Elementor\Controls_Manager::HEADING,
				'separator' => 'before',
				'condition' => array( 'countdown_mode' => 'countdown' ),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'      => 'countdown_label_typography',
				'selector'  => '{{WRAPPER}} .kdna-early-bird-countdown-label',
				'condition' => array( 'countdown_mode' => 'countdown' ),
			)
		);

		$this->add_control(
			'countdown_label_color',
			array(
				'label'     => __( 'Unit label colour', 'kdna-early-bird' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-early-bird-countdown-label' => 'color: {{VALUE}};',
				),
				'condition' => array( 'countdown_mode' => 'countdown' ),
			)
		);

		$this->add_responsive_control(
			'countdown_unit_gap',
			array(
				'label'      => __( 'Gap between units', 'kdna-early-bird' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array(
					'px' => array( 'min' => 0, 'max' => 40, 'step' => 1 ),
				),
				'default'    => array( 'unit' => 'px', 'size' => 8 ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-early-bird-countdown-units' => 'gap: {{SIZE}}{{UNIT}};',
				),
				'condition'  => array( 'countdown_mode' => 'countdown' ),
			)
		);

		$this->end_controls_section();
	}

	private function register_button_style() {
		$this->start_controls_section(
			'kdna_button_style',
			array(
				'label'     => __( 'Button', 'kdna-early-bird' ),
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => array( 'show_button' => 'yes' ),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'button_typography',
				'selector' => '{{WRAPPER}} .kdna-early-bird-button',
			)
		);

		$this->add_responsive_control(
			'button_padding',
			array(
				'label'      => __( 'Padding', 'kdna-early-bird' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', 'rem' ),
				'default'    => array(
					'top' => 10, 'right' => 18, 'bottom' => 10, 'left' => 18, 'unit' => 'px',
				),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-early-bird-button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'button_border_radius',
			array(
				'label'      => __( 'Border radius', 'kdna-early-bird' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em' ),
				'default'    => array(
					'top' => 4, 'right' => 4, 'bottom' => 4, 'left' => 4, 'unit' => 'px',
				),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-early-bird-button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'     => 'button_border',
				'selector' => '{{WRAPPER}} .kdna-early-bird-button',
			)
		);

		$this->add_responsive_control(
			'button_icon_size',
			array(
				'label'      => __( 'Icon size', 'kdna-early-bird' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array(
					'px' => array( 'min' => 8, 'max' => 60, 'step' => 1 ),
				),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-early-bird-button-icon' => 'font-size: {{SIZE}}{{UNIT}};',
					'{{WRAPPER}} .kdna-early-bird-button-icon svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'button_icon_spacing',
			array(
				'label'      => __( 'Icon spacing', 'kdna-early-bird' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array(
					'px' => array( 'min' => 0, 'max' => 40, 'step' => 1 ),
				),
				'default'    => array( 'unit' => 'px', 'size' => 8 ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-early-bird-button' => 'gap: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->start_controls_tabs( 'button_state_tabs' );

		// Normal.
		$this->start_controls_tab(
			'button_tab_normal',
			array( 'label' => __( 'Normal', 'kdna-early-bird' ) )
		);

		$this->add_control(
			'button_text_color',
			array(
				'label'     => __( 'Text colour', 'kdna-early-bird' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#ffffff',
				'selectors' => array(
					'{{WRAPPER}} .kdna-early-bird-button' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'button_background',
			array(
				'label'     => __( 'Background', 'kdna-early-bird' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#135e96',
				'selectors' => array(
					'{{WRAPPER}} .kdna-early-bird-button' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'button_border_color',
			array(
				'label'     => __( 'Border colour', 'kdna-early-bird' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-early-bird-button' => 'border-color: {{VALUE}};',
				),
			)
		);

		$this->end_controls_tab();

		// Hover.
		$this->start_controls_tab(
			'button_tab_hover',
			array( 'label' => __( 'Hover', 'kdna-early-bird' ) )
		);

		$this->add_control(
			'button_text_color_hover',
			array(
				'label'     => __( 'Text colour', 'kdna-early-bird' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#ffffff',
				'selectors' => array(
					'{{WRAPPER}} .kdna-early-bird-button:hover, {{WRAPPER}} .kdna-early-bird-button:focus' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'button_background_hover',
			array(
				'label'     => __( 'Background', 'kdna-early-bird' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#0a4b78',
				'selectors' => array(
					'{{WRAPPER}} .kdna-early-bird-button:hover, {{WRAPPER}} .kdna-early-bird-button:focus' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'button_border_color_hover',
			array(
				'label'     => __( 'Border colour', 'kdna-early-bird' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-early-bird-button:hover, {{WRAPPER}} .kdna-early-bird-button:focus' => 'border-color: {{VALUE}};',
				),
			)
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->end_controls_section();
	}

	private function register_ended_label_style() {
		$this->start_controls_section(
			'kdna_ended_label_style',
			array(
				'label' => __( 'Ended Label', 'kdna-early-bird' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'ended_label_typography',
				'selector' => '{{WRAPPER}} .kdna-early-bird-ended-label',
			)
		);

		$this->add_control(
			'ended_label_color',
			array(
				'label'     => __( 'Colour', 'kdna-early-bird' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-early-bird-ended-label' => 'color: {{VALUE}};',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Render the widget. Pulls live state from the engine and either
	 * renders the active markup, the ended markup, or nothing depending
	 * on the settings.
	 */
	protected function render() {
		$settings = $this->get_settings_for_display();

		$rule_id       = isset( $settings['rule_id'] ) ? (int) $settings['rule_id'] : 0;
		$membership_id = isset( $settings['membership_id'] ) ? (int) $settings['membership_id'] : 0;

		if ( ! class_exists( 'KDNA_Early_Bird_Engine' ) ) {
			$this->render_editor_notice( __( 'Early Bird engine is not loaded. Make sure MemberPress is active.', 'kdna-early-bird' ) );
			return;
		}

		if ( $rule_id <= 0 || $membership_id <= 0 ) {
			$this->render_editor_notice( __( 'Pick a rule and a membership in the widget settings.', 'kdna-early-bird' ) );
			return;
		}

		$engine = KDNA_Early_Bird_Engine::instance();
		$state  = $engine->get_offer_state( $membership_id );

		$covered_by_this_rule = is_array( $state ) && (int) $state['rule_id'] === $rule_id;

		if ( ! $covered_by_this_rule ) {
			$this->render_editor_notice( __( 'This membership is not currently covered by the selected rule. Check the rule is active and the membership is included in it.', 'kdna-early-bird' ) );
			return;
		}

		$live           = ! empty( $state['live'] );
		$hide_when_ended = ( ! empty( $settings['hide_when_ended'] ) && 'yes' === $settings['hide_when_ended'] );

		if ( ! $live && $hide_when_ended ) {
			if ( $this->is_editor_view() ) {
				$this->render_editor_notice( __( 'Offer has ended and "hide when ended" is on. The widget renders nothing on the front end.', 'kdna-early-bird' ) );
			}
			return;
		}

		if ( $live ) {
			$this->render_active( $settings, $state, $engine );
		} else {
			$this->render_ended( $settings );
		}
	}

	private function render_active( $settings, $state, $engine ) {
		$current_price = $engine->get_served_price( (int) $state['membership_id'] );
		$full_price    = $engine->get_stored_full_price( (int) $state['membership_id'] );
		$end_date      = isset( $state['end_date'] ) ? (string) $state['end_date'] : '';
		$cap           = isset( $state['purchase_cap'] ) ? $state['purchase_cap'] : null;
		$effective     = isset( $state['effective_count'] ) ? (int) $state['effective_count'] : 0;
		$spots         = ( is_int( $cap ) ) ? max( 0, $cap - $effective ) : null;

		$show_active_label  = isset( $settings['show_active_label'] ) && 'yes' === $settings['show_active_label'];
		$show_current_price = isset( $settings['show_current_price'] ) && 'yes' === $settings['show_current_price'];
		$show_full_price    = isset( $settings['show_full_price'] ) && 'yes' === $settings['show_full_price'];
		$show_spots         = isset( $settings['show_spots'] ) && 'yes' === $settings['show_spots'];
		$show_countdown     = isset( $settings['show_countdown'] ) && 'yes' === $settings['show_countdown'];
		$show_button        = isset( $settings['show_button'] ) && 'yes' === $settings['show_button'];
		?>
		<div class="kdna-early-bird-widget kdna-early-bird-widget--live">
			<?php if ( $show_active_label && '' !== (string) $settings['active_label_text'] ) : ?>
				<div class="kdna-early-bird-active-label">
					<?php echo esc_html( $settings['active_label_text'] ); ?>
				</div>
			<?php endif; ?>

			<?php if ( $show_current_price || $show_full_price ) : ?>
				<div class="kdna-early-bird-prices">
					<?php if ( $show_current_price ) : ?>
						<span class="kdna-early-bird-current-price">
							<?php echo esc_html( $this->format_price( $current_price ) ); ?>
						</span>
					<?php endif; ?>
					<?php if ( $show_full_price && '' !== $full_price && (string) $full_price !== (string) $current_price ) : ?>
						<span class="kdna-early-bird-full-price">
							<?php echo esc_html( $this->format_price( $full_price ) ); ?>
						</span>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php if ( $show_spots && null !== $spots ) : ?>
				<div class="kdna-early-bird-spots">
					<span class="kdna-early-bird-spots-badge">
						<?php
						$template = isset( $settings['spots_template'] ) ? (string) $settings['spots_template'] : '%d spots left';
						echo esc_html( $this->safe_sprintf_int( $template, (int) $spots ) );
						?>
					</span>
				</div>
			<?php endif; ?>

			<?php if ( $show_countdown && '' !== $end_date ) : ?>
				<?php $this->render_countdown( $settings, $end_date ); ?>
			<?php endif; ?>

			<?php if ( $show_button ) : ?>
				<?php $this->render_button( $settings ); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	private function render_ended( $settings ) {
		$label = isset( $settings['ended_label_text'] ) ? (string) $settings['ended_label_text'] : '';
		if ( '' === $label ) {
			$label = __( 'This early bird offer has ended.', 'kdna-early-bird' );
		}
		?>
		<div class="kdna-early-bird-widget kdna-early-bird-widget--ended">
			<div class="kdna-early-bird-ended-label">
				<?php echo wp_kses_post( wpautop( $label ) ); ?>
			</div>
		</div>
		<?php
	}

	private function render_countdown( $settings, $end_date ) {
		$mode = isset( $settings['countdown_mode'] ) ? (string) $settings['countdown_mode'] : 'days';

		if ( 'days' === $mode ) {
			$days_remaining = $this->days_until( $end_date );
			$template       = isset( $settings['days_template'] ) ? (string) $settings['days_template'] : '%d days left';
			?>
			<div class="kdna-early-bird-countdown kdna-early-bird-countdown--days">
				<?php echo esc_html( $this->safe_sprintf_int( $template, $days_remaining ) ); ?>
			</div>
			<?php
			return;
		}

		// Live countdown to end of the last day in the WP timezone.
		$end_iso = $this->end_of_day_iso( $end_date );
		$labels  = array(
			'days'    => isset( $settings['countdown_label_days'] ) ? (string) $settings['countdown_label_days'] : 'd',
			'hours'   => isset( $settings['countdown_label_hours'] ) ? (string) $settings['countdown_label_hours'] : 'h',
			'minutes' => isset( $settings['countdown_label_minutes'] ) ? (string) $settings['countdown_label_minutes'] : 'm',
			'seconds' => isset( $settings['countdown_label_seconds'] ) ? (string) $settings['countdown_label_seconds'] : 's',
		);
		?>
		<div class="kdna-early-bird-countdown kdna-early-bird-countdown--live" data-end="<?php echo esc_attr( $end_iso ); ?>">
			<div class="kdna-early-bird-countdown-units">
				<?php foreach ( array( 'days', 'hours', 'minutes', 'seconds' ) as $unit ) : ?>
					<div class="kdna-early-bird-countdown-unit kdna-early-bird-countdown-<?php echo esc_attr( $unit ); ?>">
						<span class="kdna-early-bird-countdown-value">00</span>
						<span class="kdna-early-bird-countdown-label"><?php echo esc_html( $labels[ $unit ] ); ?></span>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	private function render_button( $settings ) {
		$text     = isset( $settings['button_text'] ) ? (string) $settings['button_text'] : '';
		$link     = isset( $settings['button_link'] ) && is_array( $settings['button_link'] ) ? $settings['button_link'] : array();
		$url      = isset( $link['url'] ) ? (string) $link['url'] : '';
		$external = ! empty( $link['is_external'] );
		$nofollow = ! empty( $link['nofollow'] );
		$icon     = isset( $settings['button_icon'] ) ? $settings['button_icon'] : null;
		$position = isset( $settings['button_icon_position'] ) ? (string) $settings['button_icon_position'] : 'after';

		if ( '' === $text && empty( $icon ) ) {
			return;
		}

		$attrs = array(
			'class' => 'kdna-early-bird-button',
			'href'  => '' !== $url ? $url : '#',
		);
		if ( $external ) {
			$attrs['target'] = '_blank';
			$attrs['rel']    = $nofollow ? 'noopener noreferrer nofollow' : 'noopener noreferrer';
		} elseif ( $nofollow ) {
			$attrs['rel'] = 'nofollow';
		}

		$attr_html = '';
		foreach ( $attrs as $k => $v ) {
			$attr_html .= ' ' . esc_attr( $k ) . '="' . esc_attr( $v ) . '"';
		}

		$icon_html = '';
		if ( ! empty( $icon ) && class_exists( '\Elementor\Icons_Manager' ) ) {
			ob_start();
			\Elementor\Icons_Manager::render_icon( $icon, array( 'aria-hidden' => 'true' ) );
			$icon_html = ob_get_clean();
			if ( '' !== $icon_html ) {
				$icon_html = '<span class="kdna-early-bird-button-icon">' . $icon_html . '</span>';
			}
		}

		echo '<a' . $attr_html . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		if ( 'before' === $position && '' !== $icon_html ) {
			echo $icon_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		if ( '' !== $text ) {
			echo '<span class="kdna-early-bird-button-text">' . esc_html( $text ) . '</span>';
		}
		if ( 'after' === $position && '' !== $icon_html ) {
			echo $icon_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</a>';
	}

	/**
	 * Used in the editor to explain why nothing is rendering. The notice
	 * only appears inside the Elementor editor or preview view.
	 */
	private function render_editor_notice( $message ) {
		if ( ! $this->is_editor_view() ) {
			return;
		}
		echo '<div class="kdna-early-bird-widget kdna-early-bird-widget--editor-notice">';
		echo '<div class="kdna-early-bird-editor-notice">' . esc_html( $message ) . '</div>';
		echo '</div>';
	}

	private function is_editor_view() {
		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			return false;
		}
		$plugin = \Elementor\Plugin::$instance;
		if ( isset( $plugin->editor ) && method_exists( $plugin->editor, 'is_edit_mode' ) && $plugin->editor->is_edit_mode() ) {
			return true;
		}
		if ( isset( $plugin->preview ) && method_exists( $plugin->preview, 'is_preview_mode' ) && $plugin->preview->is_preview_mode() ) {
			return true;
		}
		return false;
	}

	/**
	 * Format a price using MemberPress's currency formatter when
	 * available, otherwise a plain two decimal fallback.
	 */
	private function format_price( $value ) {
		if ( '' === $value || null === $value ) {
			return '';
		}
		if ( class_exists( 'MeprUtils' ) && method_exists( 'MeprUtils', 'format_currency' ) ) {
			return MeprUtils::format_currency( (float) $value );
		}
		return number_format( (float) $value, 2, '.', '' );
	}

	private function safe_sprintf_int( $template, $number ) {
		$template = (string) $template;
		if ( false === strpos( $template, '%d' ) && false === strpos( $template, '%s' ) ) {
			return trim( (string) $number . ' ' . $template );
		}
		return sprintf( $template, (int) $number );
	}

	private function days_until( $end_date ) {
		$today_ts = strtotime( current_time( 'Y-m-d' ) );
		$end_ts   = strtotime( $end_date );
		if ( false === $today_ts || false === $end_ts || $end_ts < $today_ts ) {
			return 0;
		}
		return (int) floor( ( $end_ts - $today_ts ) / DAY_IN_SECONDS );
	}

	private function end_of_day_iso( $end_date ) {
		try {
			$tz = wp_timezone();
			$dt = new DateTime( $end_date . ' 23:59:59', $tz );
			return $dt->format( 'c' );
		} catch ( Exception $e ) {
			return $end_date . 'T23:59:59';
		}
	}

	private function get_rule_options() {
		$options = array( '' => __( '— Select a rule —', 'kdna-early-bird' ) );
		$rules   = get_posts( array(
			'post_type'        => KDNA_EARLY_BIRD_CPT,
			'post_status'      => 'publish',
			'posts_per_page'   => -1,
			'orderby'          => 'title',
			'order'            => 'ASC',
			'suppress_filters' => false,
		) );
		if ( is_array( $rules ) ) {
			foreach ( $rules as $rule ) {
				$title = '' !== $rule->post_title
					? $rule->post_title
					/* translators: %d is the rule post id. */
					: sprintf( __( 'Rule #%d', 'kdna-early-bird' ), $rule->ID );
				$options[ (string) $rule->ID ] = $title;
			}
		}
		return $options;
	}

	private function get_membership_options() {
		$options = array( '' => __( '— Select a membership —', 'kdna-early-bird' ) );
		$items   = get_posts( array(
			'post_type'        => 'memberpressproduct',
			'post_status'      => 'publish',
			'posts_per_page'   => -1,
			'orderby'          => 'title',
			'order'            => 'ASC',
			'suppress_filters' => false,
		) );
		if ( is_array( $items ) ) {
			foreach ( $items as $m ) {
				$title = '' !== $m->post_title
					? $m->post_title
					/* translators: %d is the membership post id. */
					: sprintf( __( 'Membership #%d', 'kdna-early-bird' ), $m->ID );
				$options[ (string) $m->ID ] = $title;
			}
		}
		return $options;
	}
}
