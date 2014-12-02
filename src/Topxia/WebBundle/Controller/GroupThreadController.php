<?php

namespace Topxia\WebBundle\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Topxia\Common\ArrayToolkit;
use Topxia\Common\Paginator;

class GroupThreadController extends BaseController 
{
    public function addThreadAction(Request $request,$id)
    {
        $user=$this->getCurrentUser();

        $groupinfo = $this->getGroupService()->getGroup($id);
        if(!$groupinfo){
            return $this->createMessageResponse('info','该小组已被关闭');
        }
        if(!$this->getGroupMemberRole($id)){
            return $this->createMessageResponse('info','只有小组成员可以发言');
        }

        if($request->getMethod()=="POST"){
            $thread = $request->request->all();

            $title=trim($thread['thread']['title']);
            if(empty($title)){
                $this->setFlashMessage('danger',"话题名称不能为空！");

                return $this->render('TopxiaWebBundle:Group:add-thread.html.twig',
                    array(
                    'id'=>$id,
                    'groupinfo'=>$groupinfo,
                    'is_groupmember' => $this->getGroupMemberRole($id)
                    ));
            }

            $info=array(
                'title'=>$thread['thread']['title'],
                'content'=>$thread['thread']['content'],
                'groupId'=>$id,
                'userId'=>$user['id']);
            
            $thread=$this->getThreadService()->addThread($info);
                
            return $this->redirect($this->generateUrl('group_thread_show', array(
                'id'=>$id,
                'threadId'=>$thread['id'],
                )));
            
        }
        return $this->render('TopxiaWebBundle:Group:add-thread.html.twig',
            array(
            'id'=>$id,
            'groupinfo'=>$groupinfo,
            'is_groupmember' => $this->getGroupMemberRole($id)
            ));
    }

    public function updateThreadAction(Request $request,$id,$threadId)
    {
        $user=$this->getCurrentUser();
        $groupinfo = $this->getGroupService()->getGroup($id);
        if(!$groupinfo){
            return $this->createMessageResponse('info','该小组已被关闭');
        }

        $thread=$this->getThreadService()->getThread($threadId);

        if(!$this->checkManagePermission($id,$thread)){
            return $this->createMessageResponse('info','您没有权限编辑');
        }

        $thread=$this->getThreadService()->getThread($threadId);

        if($request->getMethod()=="POST"){
            $thread = $request->request->all();
            $fields=array(
                'title'=>$thread['thread']['title'],
                'content'=>$thread['thread']['content'],);
            $thread=$this->getThreadService()->updateThread($threadId,$fields);
            if ($user->isAdmin()) {
                $threadUrl = $this->generateUrl('group_thread_show', array('id'=>$id,'threadId'=>$thread['id']), true);
                $this->getNotifiactionService()->notify($thread['userId'], 'default', "您的话题<a href='{$threadUrl}' target='_blank'><strong>“{$thread['title']}”</strong></a>被管理员编辑。<a href='{$threadUrl}' target='_blank'>点击查看</a>");
            }
            
            return $this->redirect($this->generateUrl('group_thread_show', array(
                'id'=>$id,
                'threadId'=>$threadId,
            )));
            
        }

        return $this->render('TopxiaWebBundle:Group:add-thread.html.twig',array(
            'id'=>$id,
            'groupinfo'=>$groupinfo,
            'thread'=>$thread,
            'is_groupmember' => $this->getGroupMemberRole($id)));  
    }

    public function checkUserAction(Request $request)
    {
        $currentUser = $this->getCurrentUser();

        $nickname = $request->query->get('value');
        $result = $this->getUserService()->isNicknameAvaliable($nickname);

        if ($result) {
            $response = array('success' => false, 'message' => '该用户不存在');
        } else {
            $response = array('success' => true, 'message' => '');
        }

        return $this->createJsonResponse($response);
    }

    public function collectAction(Request $request, $threadId)
    {
        $user = $this->getCurrentUser();
        if (!$user->isLogin()) {
            return $this->createErrorResponse($request, 'not_login', "您尚未登录，不能收藏话题！");
        }

        $threadMain=$this->getThreadService()->getThread($threadId);
        
        $isSetThread=$this->getThreadService()->threadCollect($user['id'],$threadId);
        
        $userShowUrl = $this->generateUrl('user_show', array('id' => $user['id']), true);
        $threadUrl = $this->generateUrl('group_thread_show', array('id'=>$threadMain['groupId'],'threadId'=>$threadMain['id']), true);

        $message = "用户<a href='{$userShowUrl}' target='_blank'>{$user['nickname']}</a>已经收藏了你的话题<a href='{$threadUrl}' target='_blank'><strong>“{$threadMain['title']}”</strong></a>！";
        $this->getNotificationService()->notify($threadMain['userId'], 'default', $message);

        return $this->createJsonResponse(true);
    }

    public function uncollectAction(Request $request, $threadId)
    {
        $user = $this->getCurrentUser();
        if (!$user->isLogin()) {
            return $this->createErrorResponse($request, 'not_login', "您尚未登录，不能收藏话题！");
        }

        $threadMain=$this->getThreadService()->getThread($threadId);
        
        $isSetThread=$this->getThreadService()->unThreadCollect($user['id'], $threadId);
        
        $userShowUrl = $this->generateUrl('user_show', array('id' => $user['id']), true);
        $threadUrl = $this->generateUrl('group_thread_show', array('id'=>$threadMain['groupId'],'threadId'=>$threadMain['id']), true);

        $message = "用户<a href='{$userShowUrl}' target='_blank'>{$user['nickname']}</a>已经取消收藏你的话题<a href='{$threadUrl}' target='_blank'><strong>“{$threadMain['title']}”</strong></a>！";
        $this->getNotificationService()->notify($threadMain['userId'], 'default', $message);

        return $this->createJsonResponse(true);    
    }

    public function groupThreadIndexAction(Request $request,$id,$threadId)
    {  
        $group = $this->getGroupService()->getGroup($id);
        if($group['status']=="close"){
            return $this->createMessageResponse('info','该小组已被关闭');
        }

        $user=$this->getCurrentUser();

        $threadMain=$this->getThreadService()->getThread($threadId);

        if (empty($threadMain)) {
            return $this->createMessageResponse('info','该话题已被管理员删除');
        }

        if($threadMain['status']=="close"){
            return $this->createMessageResponse('info','该话题已被关闭');
        }

         if ($threadMain['status']!="close") {
            $isCollected = $this->getThreadService()->isCollected($this->getCurrentUser()->id, $threadMain['id']);
        } else {
            $isCollected = false;
        }
        
        $this->getThreadService()->waveHitNum($threadId);

        if($request->query->get('post'))
        {   
            $url=$this->getPost($request->query->get('post'),$threadId,$id);
            
            return $this->redirect($url);
        }

        $owner=$this->getUserService()->getUser($threadMain['userId']);

        $filters=$this->getPostSearchFilters($request);

        $condition=$this->getPostCondition($filters['type'],$threadMain['userId'],$threadId);

        $sort=$this->getPostOrderBy($filters['sort']);

        $postCount=$this->getThreadService()->searchPostsCount($condition);

        $paginator = new Paginator(
            $this->get('request'),
            $postCount,
            30  
        );

        $post=$this->getThreadService()->searchPosts($condition,$sort,
            $paginator->getOffsetCount(),
            $paginator->getPerPageCount());

        $postMemberIds = ArrayToolkit::column($post, 'userId');

        $postId=ArrayToolkit::column($post, 'id');

        $postReplyAll=array();
        $postReply=array();
        $postReplyCount=array();
        $postReplyPaginator=array();
        foreach ($postId as $key => $value) {

            $replyCount=$this->getThreadService()->searchPostsCount(array('postId'=>$value));
            $replyPaginator = new Paginator(
                $this->get('request'),
                $replyCount,
                10  
            );

            $reply=$this->getThreadService()->searchPosts(array('postId'=>$value),array('createdTime','asc'),
                $replyPaginator->getOffsetCount(),
                $replyPaginator->getPerPageCount());

            $postReplyCount[$value]=$replyCount;
            $postReply[$value]=$reply;
            $postReplyPaginator[$value]=$replyPaginator;

            if($reply){
                $postReplyAll=array_merge($postReplyAll,ArrayToolkit::column($reply, 'userId'));
            }
        }

        $postReplyMembers=$this->getUserService()->findUsersByIds($postReplyAll);
        $postMember=$this->getUserService()->findUsersByIds($postMemberIds);

        $activeMembers=$this->getGroupService()->searchMembers(array('groupId'=>$id),
            array('postNum','DESC'),0,12);

        $memberIds = ArrayToolkit::column($activeMembers, 'userId');
        $members=$this->getUserService()->findUsersByIds($memberIds);

        $groupShareContent="";
        $defaultSetting = $this->getSettingService()->get('default', array());
        if(isset($defaultSetting['groupShareContent'])){
            $groupShareContent = str_replace("{{groupname}}", $group['title'], $defaultSetting['groupShareContent']);
            $groupShareContent = str_replace("{{threadname}}", $threadMain['title'], $groupShareContent);
        }

        $isAdopt=$this->getThreadService()->searchPosts(array('adopt'=>1,'threadId'=>$threadId),array('createdTime','desc'),0,1);


        return $this->render('TopxiaWebBundle:Group:thread.html.twig',array(
            'groupinfo' => $group,
            'isCollected' => $isCollected,
            'groupShareContent'=>$groupShareContent,
            'threadMain'=>$threadMain,
            'user'=>$user,
            'owner'=>$owner,
            'post'=>$post,
            'paginator'=>$paginator,
            'postMember'=>$postMember,
            'filters'=>$filters,
            'postCount'=>$postCount,
            'postReply'=>$postReply,
            'activeMembers'=>$activeMembers,
            'postReplyMembers'=>$postReplyMembers,
            'members'=>$members,
            'postReplyCount'=>$postReplyCount,
            'postReplyPaginator'=>$postReplyPaginator,
            'isAdopt'=>$isAdopt,
            'is_groupmember' => $this->getGroupMemberRole($id)));
    }

    public function postReplyAction(Request $request,$postId)
    {   
        $postReplyAll=array();

        $replyCount=$this->getThreadService()->searchPostsCount(array('postId'=>$postId));

        $postReplyPaginator = new Paginator(
                $this->get('request'),
                $replyCount,
                10  
            );

        $postReply=$this->getThreadService()->searchPosts(array('postId'=>$postId),array('createdTime','asc'),
                $postReplyPaginator->getOffsetCount(),
                $postReplyPaginator->getPerPageCount());

        if($postReply){
                $postReplyAll=array_merge($postReplyAll,ArrayToolkit::column($postReply, 'userId'));
        }
        $postReplyMembers=$this->getUserService()->findUsersByIds($postReplyAll);
        return $this->render('TopxiaWebBundle:Group:thread-reply-list.html.twig',array(
            'postMain' => array('id'=>$postId),
            'postReply'=>$postReply,
            'postReplyMembers'=>$postReplyMembers,
            'postReplyCount'=>$replyCount,
            'postReplyPaginator'=>$postReplyPaginator,
            ));
    }

    public function postThreadAction(Request $request,$groupId,$threadId)
    {       
            $user=$this->getCurrentUser();
            if (!$user->isLogin()) {
            return new Response($this->generateUrl('login'));
            }

            if(!$this->getGroupMemberRole($groupId)){
            $this->getGroupService()->joinGroup($user,$groupId);
            }

            $thread = $this->getThreadService()->getThread($threadId);

            $postContent=$request->request->all();

            $fromUserId = empty($postContent['fromUserId']) ? 0 : $postContent['fromUserId'];
            $content=array(
            'content'=>$postContent['content'],'fromUserId'=>$fromUserId);

            if(isset($postContent['postId'])){

                 $post=$this->getThreadService()->postThread($content,$groupId,$user['id'],$threadId,$postContent['postId']);

            }else{

                 $post=$this->getThreadService()->postThread($content,$groupId,$user['id'],$threadId);

            }       
            $userUrl = $this->generateUrl('user_show', array('id'=>$user['id']), true);
            $threadUrl = $this->generateUrl('group_thread_show', array('id'=>$groupId,'threadId'=>$thread['id']), true);
            $url=$this->getPost($post['id'],$threadId,$groupId);

            if ($user->id != $thread['userId']) {
                $this->getNotifiactionService()->notify($thread['userId'], 'default', "<a href='{$userUrl}' target='_blank'><strong>{$user['nickname']}</strong></a>在话题<a href='{$threadUrl}' target='_blank'><strong>“{$thread['title']}”</strong></a>中回复了您。<a href='{$url}' target='_blank'>点击查看</a>");
            }
            
            if (empty($fromUserId) && !empty($postContent['postId'])) {
                $post = $this->getThreadService()->getPost($postContent['postId']);
                if ($post['userId'] != $user->id && $post['userId'] != $thread['userId']) {
                    $this->getNotifiactionService()->notify($post['userId'], 'default', "<a href='{$userUrl}' target='_blank'><strong>{$user['nickname']}</strong></a>在话题<a href='{$threadUrl}' target='_blank'><strong>“{$thread['title']}”</strong></a>中回复了您。<a href='{$url}' target='_blank'>点击查看</a>");
                }
            }

            if (!empty($fromUserId) && $fromUserId != $user->id && $fromUserId != $thread['userId']) {
                $this->getNotifiactionService()->notify($postContent['fromUserId'], 'default', "<a href='{$userUrl}' target='_blank'><strong>{$user['nickname']}</strong></a>在话题<a href='{$threadUrl}' target='_blank'><strong>“{$thread['title']}”</strong></a>中回复了您。<a href='{$url}' target='_blank'>点击查看</a>");
            }

            return new Response($url);

    }
    
    public function searchResultAction(Request $request,$id)
    {   
        $keyWord=$request->query->get('keyWord') ? : "";
        $group = $this->getGroupService()->getGroup($id);

        $paginator = new Paginator(
            $this->get('request'),
            $this->getThreadService()->searchThreadsCount(array('status'=>'open','title'=>$keyWord,'groupId'=>$id)),
            15  
        );
        $threads=$this->getThreadService()->searchThreads(array('status'=>'open','title'=>$keyWord,
            'groupId'=>$id),
            array(array('createdTime','DESC')), 
            $paginator->getOffsetCount(),
            $paginator->getPerPageCount());

        $ownerIds = ArrayToolkit::column($threads, 'userId');

        $userIds = ArrayToolkit::column($threads, 'lastPostMemberId');

        $owner=$this->getUserService()->findUsersByIds($ownerIds);

        $lastPostMembers=$this->getUserService()->findUsersByIds($userIds);

        return $this->render('TopxiaWebBundle:Group:group-search-result.html.twig',array(
            'groupinfo' => $group,
            'threads'=>$threads,
            'owner'=>$owner,
            'paginator'=>$paginator,
            'lastPostMembers'=>$lastPostMembers,
            'is_groupmember' => $this->getGroupMemberRole($id)));

    }

    public function setEliteAction($threadId)
    {
        return $this->postAction($threadId,'setElite');
    }

    public function removeEliteAction($threadId)
    {
        return $this->postAction($threadId,'removeElite');
    }

    public function setStickAction($threadId)
    {
        return $this->postAction($threadId,'setStick');
    }

    public function removeStickAction($threadId)
    {
        return $this->postAction($threadId,'removeStick');
    }

    public function closeThreadAction($threadId,$memberId)
    {
        $thread=$this->getThreadService()->getThread($threadId);

        $groupMemberRole=$this->getGroupMemberRole($thread['groupId']);

        if($groupMemberRole==2 || $thread['userId']==$memberId ){
            $this->getThreadService()->closeThread($threadId);

        }
        return new Response($this->generateUrl('group_show', array(
            'id'=>$thread['groupId'],
            )));     
    }


    public function deletePostAction($postId)
    {
        $post=$this->getThreadService()->getPost($postId);

        $thread=$this->getThreadService()->getThread($post['threadId']);

        $groupMemberRole=$this->getGroupMemberRole($thread['groupId']);

        $user=$this->getCurrentUser();

        if($user['id']==$post['userId'] || $groupMemberRole==2 || $groupMemberRole==3 || $this->get('security.context')->isGranted('ROLE_ADMIN')==true){

            $this->getThreadService()->deletePost($postId);    

            $thread = $this->getThreadService()->getThread($post['threadId']);
            $threadUrl = $this->generateUrl('group_thread_show', array('id'=>$thread['groupId'],'threadId'=>$thread['id']), true);

            $this->getNotifiactionService()->notify($thread['userId'],'default',"您在话题<a href='{$threadUrl}' target='_blank'><strong>“{$thread['title']}”</strong></a>的回复被删除。"); 

        }

        return new Response($this->generateUrl('group_thread_show', array(
            'id'=>$thread['groupId'],'threadId'=>$post['threadId'],
        ))); 

    }

    public function rewardAction(Request $request,$threadId)
    {   
        $user=$this->getCurrentUser();
        $account=$this->getCashService()->getAccountByUserId($user->id,true);

        if(isset($account['cash']))
            $account['cash']=intval($account['cash']);

        if($request->getMethod()=="POST"){

            $thread=$this->getThreadService()->getThread($threadId);
            $groupMemberRole=$this->getGroupMemberRole($thread['groupId']);

            if($groupMemberRole == 2 || $groupMemberRole == 3 || $this->get('security.context')->isGranted('ROLE_ADMIN')==true){
                $amount=$request->request->get('amount');

                if(!isset($account['cash']) || $account['cash'] <  $amount ){

                    return $this->createMessageResponse('info','虚拟币余额不足!');
                    
                }

                $account=$this->getCashService()->getAccountByUserId($user->id);

                $this->getCashService()->waveDownCashField($account['id'],$amount);

                $thread['type']='reward';
                $thread['rewardCoin']=$amount;
                $this->getThreadService()->updateThread($threadId,$thread);

            }

        }

        return $this->render('TopxiaWebBundle:Group:reward-modal.html.twig',array(
            'account'=>$account,
            'threadId'=>$threadId,
            ));
    }

    public function cancelRewardAction($threadId)
    {   
        $user=$this->getCurrentUser();
        $thread=$this->getThreadService()->getThread($threadId);
        $groupMemberRole=$this->getGroupMemberRole($thread['groupId']);

        $post=$this->getThreadService()->searchPosts(array('adopt'=>1,'threadId'=>$threadId),array('createdTime','desc'),0,1);

        if($post){

            goto response;
        }

        if($groupMemberRole == 2 || $groupMemberRole == 3 || $this->get('security.context')->isGranted('ROLE_ADMIN')==true){
        
            $account=$this->getCashService()->getAccountByUserId($user->id);

            $this->getCashService()->waveCashField($account['id'],$thread['rewardCoin']);

            $thread['type']='default';
            $thread['rewardCoin']=0;
            $this->getThreadService()->updateThread($threadId,$thread);

        }

        response:
        return new Response($this->generateUrl('group_thread_show', array(
            'id'=>$thread['groupId'],
            'threadId'=>$threadId,
        )));
    }
    
    public function adoptAction($postId)
    {   

        $post=$this->getThreadService()->getPost($postId);

        $thread=$this->getThreadService()->getThread($post['threadId']);

        $groupMemberRole=$this->getGroupMemberRole($thread['groupId']);

        $user=$this->getCurrentUser();

        $isAdopt=$this->getThreadService()->searchPosts(array('adopt'=>1,'threadId'=>$post['threadId']),array('createdTime','desc'),0,1);

        if($isAdopt){

            goto response;
        }

        if($groupMemberRole==2 || $groupMemberRole==3 || $this->get('security.context')->isGranted('ROLE_ADMIN')==true){

            $post=$this->getThreadService()->updatePost($post['id'],array('adopt'=>1));

            $account=$this->getCashService()->getAccountByUserId($post['userId']);

            $this->getCashService()->waveCashField($account['id'],$thread['rewardCoin']);
        }

        response:
        return new Response($this->generateUrl('group_thread_show', array(
            'id'=>$thread['groupId'],'threadId'=>$post['threadId'],
        ))); 
    } 

    private function postAction($threadId,$action)
    {
        $thread=$this->getThreadService()->getThread($threadId);
        $groupMemberRole=$this->getGroupMemberRole($thread['groupId']);

        if($groupMemberRole==2 || $groupMemberRole==3 || $this->get('security.context')->isGranted('ROLE_ADMIN')==true ){
            $threadUrl = $this->generateUrl('group_thread_show', array('id'=>$thread['groupId'],'threadId'=>$thread['id']), true);
            if($action=='setElite'){
               $this->getThreadService()->setElite($threadId);
               $this->getNotifiactionService()->notify($thread['userId'],'default',"您的话题<a href='{$threadUrl}' target='_blank'><strong>“{$thread['title']}”</strong></a>被设为精华。"); 
            }
            if($action=='removeElite'){
               $this->getThreadService()->removeElite($threadId);
               $this->getNotifiactionService()->notify($thread['userId'],'default',"您的话题<a href='{$threadUrl}' target='_blank'><strong>“{$thread['title']}”</strong></a>被取消精华。"); 
            }
            if($action=='setStick'){
               $this->getThreadService()->setStick($threadId);
               $this->getNotifiactionService()->notify($thread['userId'],'default',"您的话题<a href='{$threadUrl}' target='_blank'><strong>“{$thread['title']}”</strong></a>被置顶。"); 
            }
            if($action=='removeStick'){
               $this->getThreadService()->removeStick($threadId);
               $this->getNotifiactionService()->notify($thread['userId'],'default',"您的话题<a href='{$threadUrl}' target='_blank'><strong>“{$thread['title']}”</strong></a>被取消置顶。"); 
            }

        }
        return new Response($this->generateUrl('group_thread_show', array(
            'id'=>$thread['groupId'],
            'threadId'=>$threadId,
        )));
    }
    private function getPost($postId,$threadId,$id)
    {   
        $post=$this->getThreadService()->getPost($postId);
        if($post['postId']!=0)$postId=$post['postId'];
        $count=$this->getThreadService()->searchPostsCount(array('threadId'=>$threadId,'status'=>'open','id'=>$postId,'postId'=>0));

        $page=floor(($count)/30)+1;
   
        $url=$this->generateUrl('group_thread_show',array('id'=>$id,'threadId'=>$threadId));

        $url=$url."?page=$page#post-$postId";
        return $url;
    }

    private function getThreadService()
    {
        return $this->getServiceKernel()->createService('Group.ThreadService');
    }
    protected function getUserService()
    {
        return $this->getServiceKernel()->createService('User.UserService');
    }
    private function getGroupService() 
    {
        return $this->getServiceKernel()->createService('Group.GroupService');
    }

    private function getPostSearchFilters($request)
    {
        $filters=array();

        $filters['type']=$request->query->get('type');

        if(!in_array($filters['type'], array('all','onlyOwner'))){

            $filters['type']='all';
        }

        $filters['sort']=$request->query->get('sort');

        if(!in_array($filters['sort'], array('asc','desc'))){

            $filters['sort']='asc';
        }

        return $filters;
    }
    private function getPostCondition($filters,$ownId,$threadId)
    {
        if($filters=='all') return array('threadId'=>$threadId,'status'=>'open','postId'=>0);

        if($filters=='onlyOwner') return array('threadId'=>$threadId,'status'=>'open','userId'=>$ownId,'postId'=>0);

        return false;

    }
    private function getPostOrderBy($sort)
    {
        if($sort=='asc') return array('createdTime','asc');

        if($sort=='desc') return array('createdTime','desc');
    }

    protected function getSettingService()
    {
        return $this->getServiceKernel()->createService('System.SettingService');
    }

    private function getNotifiactionService()
    {
        return $this->getServiceKernel()->createService('User.NotificationService');
    }

        protected function getNotificationService()
    {
        return $this->getServiceKernel()->createService('User.NotificationService');
    }

     private function filterSort($sort)
    {
        switch ($sort) {
            case 'byPostNum':
                $orderBys=array(
                    array('isStick','DESC'),
                    array('postNum','DESC'),
                    array('createdTime','DESC'),
                );
                break;
            case 'byStick':
                $orderBys=array(
                    array('isStick','DESC'),
                    array('createdTime','DESC'),
                );
                break;
            case 'byCreatedTime':
                $orderBys=array(
                    array('isStick','DESC'),
                    array('createdTime','DESC'),
                );
                break;
            case 'byLastPostTime':
                $orderBys=array(
                    array('isStick','DESC'),
                    array('lastPostTime','DESC'),
                );
                break;
            case 'byPostNum':
                $orderBys=array(
                    array('isStick','DESC'),
                    array('postNum','DESC'),
                );
                break;
            default:
                throw $this->createServiceException('参数sort不正确。');
        }
        return $orderBys;
    }

    private function getGroupMemberRole($userId)
    {
       $user = $this->getCurrentUser();

       if (!$user['id']) return 0;

       if ($this->getGroupService()->isOwner($userId, $user['id'])) return 2;

       if ($this->getGroupService()->isAdmin($userId, $user['id'])) return 3;

       if ($this->getGroupService()->isMember($userId, $user['id'])) return 1;

       return 0;
    }

    private function checkManagePermission($id,$thread)
    {   
        $user=$this->getCurrentUser();

        if($this->get('security.context')->isGranted('ROLE_ADMIN')==true) return true;
        if($this->getGroupService()->isOwner($id, $user['id'])) return true;
        if($this->getGroupService()->isAdmin($id, $user['id'])) return true;
        if($thread['userId']==$user['id']) return true;
        return false;
    }

    private function getCashService(){
      
        return $this->getServiceKernel()->createService('Cash.CashService');
    }

}
