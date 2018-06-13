<?php
/**
 * Course about page
 * Show information about a course.
 *
 * @author Alex Aragon Calixto <alex.aragon@beeznest.com>
 *
 * @package chamilo.course
 */


use Chamilo\CoreBundle\Entity\Course;
use Chamilo\CoreBundle\Entity\CourseRelUser;
use Chamilo\CourseBundle\Entity\CCourseDescription;
use Chamilo\UserBundle\Entity\User;
use Chamilo\CoreBundle\Entity\ExtraField;

$cidReset = true;

require_once __DIR__.'/../inc/global.inc.php';

$courseId = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;
$token = Security::get_existing_token();
$em = Database::getManager();
//userID
$user_id = api_get_user_id();

/** @var Course $course */
$course = $em->find('ChamiloCoreBundle:Course', $courseId);
/** @var User $userRepo */
$userRepo = $em->getRepository('ChamiloUserBundle:User');
$fieldsRepo = $em->getRepository('ChamiloCoreBundle:ExtraField');
$fieldTagsRepo = $em->getRepository('ChamiloCoreBundle:ExtraFieldRelTag');

/** @var CCourseDescription $courseDescription */
$courseDescriptionTools = $em->getRepository('ChamiloCourseBundle:CCourseDescription')
    ->findBy(
        [
            'cId' => $course->getId(),
            'sessionId' => 0,
        ],
        [
            'id' => 'DESC',
            'descriptionType' => 'ASC',
        ]
    );

$courseValues = new ExtraFieldValue('course');
$userValues = new ExtraFieldValue('user');

$urlCourse = api_get_path(WEB_PATH). 'main/course/about.php?course_id='.$courseId;

$courseTeachers = $course->getTeachers();

$teachersData = [];

/** @var CourseRelUser $teacherSubscription */
foreach ($courseTeachers as $teacherSubscription) {
    $teacher = $teacherSubscription->getUser();
    $userData = [
        'complete_name' => $teacher->getCompleteName(),
        'image' => UserManager::getUserPicture(
            $teacher->getId(),
            USER_IMAGE_SIZE_ORIGINAL
        ),
        'diploma' => $teacher->getDiplomas(),
        'openarea' => $teacher->getOpenarea()
    ];

    $teachersData[] = $userData;

}

$tagField = $fieldsRepo->findOneBy([
    'extraFieldType' => ExtraField::COURSE_FIELD_TYPE,
    'variable' => 'tags',
]);

$courseTags = [];

if (!is_null($tagField)) {
    $courseTags = $fieldTagsRepo->getTags($tagField, $courseId);
}

$courseDescription = $courseObjectives = $courseTopics = $courseMethodology = $courseMaterial = $courseResources = $courseAssessment = '';
$courseCustom = [];
foreach ($courseDescriptionTools as $descriptionTool) {
    switch ($descriptionTool->getDescriptionType()) {
        case CCourseDescription::TYPE_DESCRIPTION:
            $courseDescription = $descriptionTool->getContent();
            break;
        case CCourseDescription::TYPE_OBJECTIVES:
            $courseObjectives = $descriptionTool;
            break;
        case CCourseDescription::TYPE_TOPICS:
            $courseTopics = $descriptionTool;
            break;
        case CCourseDescription::TYPE_METHODOLOGY:
            $courseMethodology = $descriptionTool;
            break;
        case CCourseDescription::TYPE_COURSE_MATERIAL:
            $courseMaterial = $descriptionTool;
            break;
        case CCourseDescription::TYPE_RESOURCES:
            $courseResources = $descriptionTool;
            break;
        case CCourseDescription::TYPE_ASSESSMENT:
            $courseAssessment = $descriptionTool;
            break;
        case CCourseDescription::TYPE_CUSTOM:
            $courseCustom[] = $descriptionTool;
            break;
    }
}

$topics = [
    'objectives' => $courseObjectives,
    'topics' => $courseTopics,
    'methodology' => $courseMethodology,
    'material' => $courseMaterial,
    'resources' => $courseResources,
    'assessment' => $courseAssessment,
    'custom' => array_reverse($courseCustom)
];

$subscriptionUser = CourseManager::is_user_subscribed_in_course($user_id,$course->getCode());

$plugin = BuyCoursesPlugin::create();
$checker = $plugin->isEnabled();
$courseIsPremium = null;
if ($checker) {
    $courseIsPremium = $plugin->getItemByProduct(
        $courseId,
        BuyCoursesPlugin::PRODUCT_TYPE_COURSE
    );
}

$courseItem = [
    'code' => $course->getCode(),
    'title' => $course->getTitle(),
    'description' => $courseDescription,
    'image' => $course->getPicturePath(true),
    'syllabus' => $topics,
    'tags' => $courseTags,
    'teachers' => $teachersData,
    'extra_fields' => $courseValues->getAllValuesForAnItem(
        $course->getId(),
        null,
        true
    ),
    'subscription' => $subscriptionUser
];

$metaInfo =  '<meta property="og:url" content="'.$urlCourse.'" />';
$metaInfo .= '<meta property="og:type" content="website" />';
$metaInfo .= '<meta property="og:title" content="'.$courseItem['title'].'" />';
$metaInfo .= '<meta property="og:description" content="'.$courseDescription.'" />';
$metaInfo .= '<meta property="og:image" content="'.$courseItem['image'].'" />';

$htmlHeadXtra[] = $metaInfo;
$htmlHeadXtra[] = api_get_asset('readmore-js/readmore.js');

$template = new Template($course->getTitle(), true, true, false, true, false);
$template->assign('course', $courseItem);
$essence = Essence\Essence::instance();
$template->assign('essence', $essence);
$template->assign('is_premium', $courseIsPremium);
$template->assign('token', $token);
$template->assign('url', $urlCourse);
$layout = $template->get_template('course_home/about.tpl');
$content = $template->fetch($layout);
$template->assign('content', $content);
$template->display_one_col_template();