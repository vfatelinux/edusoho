<?php

namespace Tests\Unit\Activity\Dao;

use Tests\Unit\Base\BaseDaoTestCase;

class ActivityLearnLogDaoTest extends BaseDaoTestCase
{
    public function testSearch()
    {
        $expected = array();
        $expected[] = $this->mockDataObject();
        $expected[] = $this->mockDataObject(array('activityId' => 2));
        $expected[] = $this->mockDataObject(array('userId' => 2));

        $testConditions = array(
            array(
                'condition' => array(),
                'expectedResults' => $expected,
                'expectedCount' => 3,
            ),
            array(
                'condition' => array('userId' => 1),
                'expectedResults' => array($expected[0], $expected[1]),
                'expectedCount' => 2,
            ),
            array(
                'condition' => array('activityId' => 1),
                'expectedResults' => array($expected[0], $expected[2]),
                'expectedCount' => 2,
            ),
            array(
                'condition' => array('userId' => 1, 'activityId' => 1),
                'expectedResults' => array($expected[0]),
                'expectedCount' => 1,
            ),
            array(
                'condition' => array('userId' => 2, 'activityId' => 2),
                'expectedResults' => array(),
                'expectedCount' => 0,
            ),
        );

        $this->searchTestUtil($this->getDao(), $testConditions, $this->getCompareKeys());
    }

    public function testSumLearnedTimeByActivityId()
    {
        $expected = array();
        $this->mockDataObject(array('activityId' => 1));
        $expected[] = $this->mockDataObject(array('activityId' => 2));
        $expected[] = $this->mockDataObject(array('activityId' => 2));

        $res = $this->getDao()->sumLearnedTimeByActivityId(2);

        $this->assertEquals($this->getSums($expected), $res);
    }

    public function testSumLearnedTimeByActivityIdAndUserId()
    {
        $expected = array();
        $expected[] = $this->mockDataObject();
        $expected[] = $this->mockDataObject();
        $expected[] = $this->mockDataObject();

        $res = $this->getDao()->sumLearnedTimeByActivityIdAndUserId(1, 1);

        $this->assertEquals($this->getSums($expected), $res);
    }

    public function testSumLearnedTimeByCourseIdAndUserId()
    {
        $mockActivity = $this->mockActivity();

        $expected = array();
        $expected[] = $this->mockDataObject();
        $expected[] = $this->mockDataObject();
        $expected[] = $this->mockDataObject();

        $res = $this->getDao()->sumLearnedTimeByCourseIdAndUserId(1, 1);

        $this->assertEquals(3, $res);
    }

    public function testFindByActivityIdAndUserIdAndEvent()
    {
        $expected = array();
        $expected[] = $this->mockDataObject();
        $expected[] = $this->mockDataObject();
        $expected[] = $this->mockDataObject();

        $res = $this->getDao()->findByActivityIdAndUserIdAndEvent(1, 1, 'ffff');

        foreach ($expected as $key => $val) {
            $this->assertArrayEquals($val, $res[$key], $this->getCompareKeys());
        }
    }

    public function testCountLearnedDaysByCourseIdAndUserId()
    {
        $mockActivity = $this->mockActivity();

        $expected = array();
        $expected[] = $this->mockDataObject();
        $expected[] = $this->mockDataObject();
        $expected[] = $this->mockDataObject();

        $res = $this->getDao()->countLearnedDaysByCourseIdAndUserId(1, 1);

        $this->assertEquals(1, $res);
    }

    public function testSumLearnTime()
    {
        $expected = array();
        $expected[] = $this->mockDataObject();
        $expected[] = $this->mockDataObject(array('activityId' => 2));
        $expected[] = $this->mockDataObject(array('userId' => 2));

        $res = array();
        $res[] = $this->getDao()->sumLearnTime(array());
        $res[] = $this->getDao()->sumLearnTime(array('userId' => 2));
        $res[] = $this->getDao()->sumLearnTime(array('activityId' => 2, 'userId' => 2));

        $this->assertEquals(3, $res[0]);
        $this->assertEquals(1, $res[1]);
        $this->assertEquals(0, $res[2]);
    }

    protected function fetchAndAssembleIds(array $rawInput)
    {
        $res = array();
        foreach ($rawInput as $val) {
            $res[] = $val['id'];
        }

        return $res;
    }

    protected function getSums(array $rawInput)
    {
        $sum = 0;
        foreach ($rawInput as $val) {
            if (is_array($val)) {
                if (isset($val['learnedTime'])) {
                    $sum += $val['learnedTime'];
                } else {
                    throw new \Codeages\Biz\Framework\Dao\DaoException('database table error');
                }
            } elseif (is_numeric($val)) {
                $sum += $val;
            } else {
                throw new \Codeages\Biz\Framework\Dao\DaoException($val);
            }
        }

        return $sum;
    }

    protected function getDefaultMockFields()
    {
        return array(
            'activityId' => 1,
            'userId' => 1,
            'mediaType' => 'video',
            'event' => 'ffff',
            'data' => array('a'),
            'learnedTime' => 1,
            'courseTaskId' => 1,
        );
    }

    private function mockActivity($fields = array())
    {
        $defaultFields = array(
            'title' => 'asdf',
            'remark' => 'asdf',
            'mediaId' => 1,
            'mediaType' => 'video',
            'content' => 'asdf',
            'length' => 1,
            'fromCourseId' => 1,
            'fromCourseSetId' => 1,
            'fromUserId' => 1,
            'startTime' => 1,
            'endTime' => 10,
        );

        $fields = array_merge($defaultFields, $fields);

        return $this->getActivityDao()->create($fields);
    }

    private function getActivityDao()
    {
        return $this->createDao('Activity:ActivityDao');
    }
}
