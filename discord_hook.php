<?php

namespace app;

class DiscordHook
{
    /* Question type constants */
    const QT_GUESS_FROM_VIDEO = 0;
    const QT_GUESS_VIDEO_FROM_WORD = 1;
    const QT_TYPE_FROM_VIDEO = 2;
    const QT_SIGN_FROM_WORD = 3;

    const VERSION = 'discord_hook 1.0';

    /**
     * Merge categories into main categories
     * (part before first / in category label)
     */
    private static function merge_categories(array $list): array
    {
    	$result = [];

    	foreach ($list as $item) {
    		$main_label = trim(explode('/', $item['category_label'])[0]);
    		if (!array_key_exists($main_label, $result)) {
    			$result[$main_label] = [
    				'ids' => [],
    				'label' => $main_label,
    				'count' => 0,
    			];
    		}
    		$result[$main_label]['ids'][] = $item['category_id'];
    		$result[$main_label]['count'] += $item['count'];
    	}

    	return $result;
    }

    /**
     * Get a random key into list with string keys
     */
    private static function random_key(array $list): string
    {
    	$keys = array_keys($list);
    	$i = random_int(0, count($keys) - 1);
    	return $keys[$i];
    }

    /**
     * Generate random question
     * @param array $category_ids Array of category ids to include
     * @param int $n_answers Number of answers to include
     */
    private static function generate_question(array $category_ids, int $n_answers): array
    {
    	$type = random_int(0, 3);
    	if (in_array($type, [self::QT_TYPE_FROM_VIDEO, self::QT_SIGN_FROM_WORD])) {
    		$n_answers = 1;
    	}
    	$correct_index = random_int(0, $n_answers - 1);
    	$result = [
    		'type' => $type,
    		'correct_index' => $correct_index,
    		'words' => [],
    	];
    	$words = json_decode(file_get_contents(
    		'https://tspquiz.se/api/?action=random' .
    		'&count=' . urlencode($n_answers) .
    		'&excludeUncommon=1' .
    		'&categories=' . urlencode('[' . implode(',', $category_ids) . ']')), true);
    	if (!is_array($words)) {
    		throw new \Exception('Loading words failed');
    	}
    	foreach ($words as $word) {
    		$result['words'][] = $word['id'];
    	}
    	return $result;
    }

    /**
     * Generate random quiz
     * @param array $category_ids Array of category ids to include
     */
    private static function generate_quiz(array $category_ids): array
    {
    	$n_question = random_int(5, 10);
    	$n_answers = random_int(3, 5);
    	$result = [
    		'version' => 1,
    		'questions' => [],
    	];
    	for ($i = 0; $i < $n_question; $i++) {
    		$type = random_int(0, 3);
    		$result['questions'][] = self::generate_question($category_ids, $n_answers);
    	}
    	return $result;
    }

    /**
     * Create share link for given quiz data
     */
    private static function share_link(array $quiz): string
    {
    	$data_str = json_encode($quiz);
    	$base64_str = base64_encode($data_str);
    	$url = 'https://tspquiz.se/app' .
    		'?loadQuiz=' . urlencode($base64_str) .
    		'#/start';
    	return $url;
    }

    private static function compose_discord_message(array $quiz, string $category_label): string
    {
    	$share_link = self::share_link($quiz);
    	$n_questions = count($quiz['questions']);
    	return json_encode([
    		'username' => 'TSP Quiz',
    		'content' => "$n_questions frågor om $category_label",
    		'embeds' => [
    			[
    				'title' => 'Öppna i TSP Quiz',
    				'description' => '',
    				'url' => $share_link,
    			],
    		],
    	]);
    }

    private static function discord_post(string $msg, string $webhook): void
    {
    	$ch = curl_init($webhook);
    	$data = 'payload_json=' . urlencode($msg).'';
    
    	if(isset($ch)) {
    		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    		$result = curl_exec($ch);
    		curl_close($ch);
    		print_r($result);
    	}
    }

    /**
     * Generate random quiz and post to discord using given webhook
     */
    public static function run($webhook): void
    {
        $category_list = json_decode(file_get_contents(
            'https://tspquiz.se/api/?action=list-category' .
            '&appVersion='.urlencode(self::VERSION)), true);
        $main_category_list = self::merge_categories($category_list);
        $random_category = $main_category_list[self::random_key($main_category_list)];

        $quiz = self::generate_quiz($random_category['ids']);
        $msg = self::compose_discord_message($quiz, $random_category['label']);

        self::discord_post($msg, $webhook);
    }
}