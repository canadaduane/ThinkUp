<?php
/*
 Plugin Name: Bestie
 Description: Who you've interacted with most in the past month.
 When: 17th for Twitter, 19th for Facebook, 21st for Instagram
 */

/**
 *
 * ThinkUp/webapp/plugins/insightsgenerator/insights/bestie.php
 *
 * Copyright (c) 2014-2015 Gina Trapani
 *
 * LICENSE:
 *
 * This file is part of ThinkUp (http://thinkup.com).
 *
 * ThinkUp is free software: you can redistribute it and/or modify it under the terms of the GNU General Public
 * License as published by the Free Software Foundation, either version 2 of the License, or (at your option) any
 * later version.
 *
 * ThinkUp is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more
 * details.
 *
 * You should have received a copy of the GNU General Public License along with ThinkUp.  If not, see
 * <http://www.gnu.org/licenses/>.
 *
 * Copyright (c) 2014-2015 Gina Trapani
 *
 * @author Gina Trapani <ginatrapani at gmail dot com>
 * @license http://www.gnu.org/licenses/gpl.html
 * @copyright 2014-2015 Gina Trap9ani
 */

class BestieInsight extends InsightPluginParent implements InsightPlugin {
    /**
     * Slug for this insight
     **/
    var $slug = 'bestie';

    public function generateInsight(Instance $instance, User $user, $last_week_of_posts, $number_days) {
        parent::generateInsight($instance, $user, $last_week_of_posts, $number_days);
        $this->logger->logInfo("Begin generating insight", __METHOD__.','.__LINE__);

        $since_date = date("Y-m-d");
        $filename = basename(__FILE__, ".php");
        $regenerate = false;

        switch ($instance->network) {
            case 'twitter':
                $day_of_month = 17;
                break;
            case 'facebook':
                $day_of_month = 19;
                break;
            case 'instagram':
                $day_of_month = 21;
                break;
            default:
                $day_of_month = 23;
        }

        $should_generate_insight = self::shouldGenerateMonthlyInsight($this->slug, $instance,
            $insight_date=$since_date, $regenerate_existing_insight=$regenerate, $day_of_month = $day_of_month);

//        if (true) {
        if ($should_generate_insight) {
            $this->logger->logInfo("Should generate", __METHOD__.','.__LINE__);

            $post_dao = DAOFactory::getDAO('PostDAO');
            $bestie = $post_dao->getBestie($instance, 30);

            if (isset($bestie)) {
                //Assume a streak of one, unless baselines tell us differently
                $streak = 1;
                //Does past bestie exist?
                $insight_baseline_dao = DAOFactory::getDAO('InsightBaselineDAO');
                $last_bestie_id = $insight_baseline_dao->getInsightBaseline("bestie_last_bestie_id", $instance->id,
                    date(strtotime('-1 month')));
                $last_bestie_count = $insight_baseline_dao->getInsightBaseline("bestie_last_bestie_count",
                    $instance->id, date(strtotime('-1 month')));
                if (isset($last_bestie_id) && isset($last_bestie_count)) {
                    $this->logger->logInfo("Streak baselines exist", __METHOD__.','.__LINE__);
                    if ($last_bestie_id->value == $bestie['user_id']) {
                        $this->logger->logInfo("Streak baseline matches", __METHOD__.','.__LINE__);
                        $streak = intval($last_bestie_count->value) + 1;
                    }
                }

                $insight = new Insight();
                $insight->instance_id = $instance->id;
                $insight->slug = $this->slug;
                $insight->date = $since_date;;

                $network = $instance->network;

                $copy_endings = array(
                    'What a pair!',
                    'You two!',
                    'Peas in a pod!',
                    'Best buds!',
                    'What pals!',
                    'OMG get a room!'
                );

                $copy_ender = $this->getVariableCopy($copy_endings);

                $copy_first_bestie = array(
                    'twitter' => array(
                        'headline' => "@%bestie is %username's Twitter bestie",
                        'body' => "Best friends chat each other up about every topic under the sun&mdash;just like ".
                            "@%bestie and %username. In the past month, @%bestie tweeted at %username ".
                            "<strong>%b_to_u times</strong> and %username replied ".
                            "<strong>%u_to_b times</strong>. ".$copy_ender
                    ),
                    'facebook' => array(
                        'headline' => "%bestie is %username's Facebook BFF",
                        'body' => "Friends comment on friends' status updates&mdash;friends like ".
                            "%bestie and %username. In the past month, %bestie commented on %username's ".
                            "status updates <strong>%b_to_u times</strong>, more than ".
                            "anyone else. ".$copy_ender
                    ),
                    'instagram' =>array(
                        'headline' => "%bestie is %username's Instagram bestie",
                        'body' => "Friends have a lot to say about friends' Instagram photos: like ".
                            "%bestie and %username. In the past month, %bestie commented on %username's ".
                            "photos <strong>%b_to_u times</strong>, more than ".
                            "anyone else. ".$copy_ender
                    )
                );

                $copy_streak_bestie = array(
                    'twitter' => array(
                        'headline' => "@%bestie is still %username's Twitter bestie",
                        'body' => "BFFs love to chat, and for <strong>%streak months straight</strong>, ".
                            "@%bestie has been %username's bestie. In the past month, @%bestie tweeted at %username ".
                            "<strong>%b_to_u times</strong> and @%bestie replied ".
                            "<strong>%u_to_b times</strong>. ".$copy_ender
                    ),
                    'facebook' => array(
                        'headline' => "%bestie is %username's Facebook BFF",
                        'body' => "BFFs talk it ups, and for <strong>%streak months straight</strong> @$bestie ".
                            "has been %username's bestie. In the past month, %bestie commented on %username's ".
                            "status updates <strong>%b_to_u times</strong>, more than ".
                            "anyone else. ".$copy_ender
                    ),
                    'instagram' =>array(
                        'headline' => "%bestie is still %username's Instagram bestie",
                        'body' => "BFFs tell each other what they think, and for ".
                            "<strong>%streak months straight</strong> @$bestie has been %username's Instagram bestie. ".
                            "In the past month, %bestie commented on %username's photos ".
                            "<strong>%b_to_u times</strong>, more than anyone else. ".$copy_ender
                    )
                );

                if ($streak == 1) { //No streak
                    $headline = $this->getVariableCopy(
                        array(
                            $copy_first_bestie[$network]['headline']
                        ),
                        array(
                            'bestie' => ((isset($bestie['user_name'])?$bestie['user_name']:""))
                        )
                    );

                    $insight_text = $this->getVariableCopy(
                        array(
                            $copy_first_bestie[$network]['body']
                        ),
                        array(
                            'bestie' => ((isset($bestie['user_name'])?$bestie['user_name']:"")),
                            'u_to_b' => ((isset($bestie['total_replies_to'])?$bestie['total_replies_to']:"")),
                            'b_to_u' => ((isset($bestie['total_replies_from'])?$bestie['total_replies_from']:"")),
                        )
                    );
                } else {
                    $headline = $this->getVariableCopy(
                        array(
                            $copy_streak_bestie[$network]['headline']
                        ),
                        array(
                            'bestie' => ((isset($bestie['user_name'])?$bestie['user_name']:""))
                        )
                    );

                    $insight_text = $this->getVariableCopy(
                        array(
                            $copy_streak_bestie[$network]['body']
                        ),
                        array(
                            'bestie' => ((isset($bestie['user_name'])?$bestie['user_name']:"")),
                            'u_to_b' => ((isset($bestie['total_replies_to'])?$bestie['total_replies_to']:"")),
                            'b_to_u' => ((isset($bestie['total_replies_from'])?$bestie['total_replies_from']:"")),
                            'streak' => $streak
                        )
                    );
                }

                $insight->headline = $headline;
                $insight->text = $insight_text;
                $insight->filename = basename(__FILE__, ".php");
                $insight->emphasis = Insight::EMPHASIS_HIGH;
                if (isset($bestie['avatar'])) {
                    $insight->header_image = $bestie['avatar'];
                }
                $this->insight_dao->insertInsight($insight);

                //Update baselines for next time
                $insight_baseline_dao->insertInsightBaseline("bestie_last_bestie_id", $instance->id, $bestie['user_id'],
                    $this->insight_date);
                $insight_baseline_dao->insertInsightBaseline("bestie_last_bestie_count", $instance->id, $streak,
                    $this->insight_date);

            }
        }
        $this->logger->logInfo("Done generating insight", __METHOD__.','.__LINE__);
    }
}

$insights_plugin_registrar = PluginRegistrarInsights::getInstance();
$insights_plugin_registrar->registerInsightPlugin('BestieInsight');
