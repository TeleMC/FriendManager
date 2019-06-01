<?php
namespace FriendManager;

use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use UiLibrary\UiLibrary;

class FriendManager extends PluginBase {

    private static $instance = null;
    public $pre = "§e•";

    public static function getInstance() {
        return self::$instance;
    }

    public function onLoad() {
        self::$instance = $this;
    }

    public function onEnable() {
        date_default_timezone_set("Asia/Seoul");
        @mkdir($this->getDataFolder());
        @mkdir($this->getChatPath());
        $this->getServer()->getPluginManager()->registerEvents(new EventListener($this), $this);
        $this->friend = new Config($this->getDataFolder() . "friend.yml", Config::YAML);
        $this->fdata = $this->friend->getAll();
        $this->ui = UiLibrary::getInstance();
    }

    public function getChatPath() {
        return $this->getDataFolder() . "chat/";
    }

    public function onDisable() {
        $this->save();
    }

    public function save() {
        $this->friend->setAll($this->fdata);
        $this->friend->save();
    }

    public function isExistPlayer(string $name) {
        return file_exists($this->getServer()->getDataPath() . "players/" . mb_strtolower($name) . ".dat");
    }

    public function register(string $name) {
        $name = mb_strtolower($name);
        @mkdir($this->getChatPath() . $name . "/");
        $this->fdata[$name] = [];
        $this->fdata[$name]["수신여부"] = true;
        $this->fdata[$name]["친구"] = [];
        $this->fdata[$name]["그룹"] = [];
        $this->fdata[$name]["그룹"]["일반"] = [];
        $this->fdata[$name]["수신목록"] = [];
        $this->fdata[$name]["새메세지"] = [];
    }

    public function FriendUI(Player $player) {
        $form = $this->ui->SimpleForm(function (Player $player, array $data) {
            if (!isset($data[0])) return false;
            if ($data[0] == 0) {
                $this->FriendInfoUI($player);
            }
            if ($data[0] == 1) {
                if ($this->getRequestFriend($player->getName()) == true) {
                    $this->setRequestFriend($player->getName(), false);
                    $player->sendMessage("{$this->pre} 친구 수신을 거부하였습니다.");
                } else {
                    $this->setRequestFriend($player->getName(), true);
                    $player->sendMessage("{$this->pre} 친구 수신을 허용하였습니다.");
                }
            }
            if ($data[0] == 2) {
                $this->RequestFriendUI($player);
            }
            if ($data[0] == 3) {
                $this->ChatUI($player);
            }
            if ($data[0] == 4) {
                $this->AddFriendUI($player);
            }
            if ($data[0] == 5) {
                $this->DelFriendUI($player);
            }
            if ($data[0] == 6) {
                $this->AddGroupUI($player);
            }
            if ($data[0] == 7) {
                $this->DelGroupUI($player);
            }
            if ($data[0] == 8) {
                $this->SetGroupUI($player);
            }
        });
        $form->setTitle("Tele Friend");
        $form->addButton("§l친구 정보\n§r§8친구 정보를 확인합니다.");
        $form->addButton("§l친구 수신여부\n§r§8친구 수신여부를 결정합니다.");
        $form->addButton("§l친구 수신목록\n§r§8친구 수신목록을 합니다.");
        $form->addButton("§l채팅\n§r§8친구와 채팅을 합니다.");
        $form->addButton("§l친구 추가\n§r§8친구를 추가합니다.");
        $form->addButton("§l친구 제거\n§r§8친구를 제거합니다.");
        $form->addButton("§l친구그룹 추가\n§r§8친구그룹을 추가합니다.");
        $form->addButton("§l친구그룹 제거\n§r§8친구그룹을 제거합니다.");
        $form->addButton("§l친구 그룹 설정\n§r§8친구 그룹을 설정합니다.");
        $form->addButton("§l닫기");
        $form->sendToPlayer($player);
    }

    private function FriendInfoUI(Player $player) {
        $form = $this->ui->SimpleForm(function (Player $player, array $data) {
            if (!isset($data[0]) || !isset($this->list[$player->getName()]) || !isset($this->list[$player->getName()][$data[0]])) return false;
            $group = $this->list[$player->getName()][$data[0]];
            unset($this->list[$player->getName()]);
            $this->FriendInfoUI_1($player, $group);
            return true;
        });
        $form->setTitle("Tele Friend");
        $count = 0;
        foreach ($this->getGroups($player->getName()) as $group => $friends) {
            $this->list[$player->getName()][$count] = $group;
            $form->addButton("§l{$group}\n§r§8그룹에 분류된 친구: {$this->getGroupFriendCount($player->getName(), $group)}");
            $count++;
        }
        $form->sendToPlayer($player);
    }

    private function FriendInfoUI_1(Player $player, string $group) {
        $form = $this->ui->SimpleForm(function (Player $player, array $data) {
        });
        $form->setTitle("Tele Friend");
        $text = "";
        if ($this->getFriendCount($player->getName()) <= 0)
            $text .= "§l§c▶ §r§f친구가 없습니다..";
        else {
            foreach ($this->getGroupFriends($player->getName(), $group) as $key => $friend) {
                $text .= "§l§c▶ §r§f{$friend}";
                if ($this->getServer()->getPlayer($friend) instanceof Player)
                    $text .= " §a온라인\n\n";
                else
                    $text .= " §8오프라인\n\n";
            }
        }
        $form->setContent($text);
        $form->sendToPlayer($player);
    }

    public function getFriendCount(string $name) {
        $name = mb_strtolower($name);
        if (!$this->isRegistered($name))
            return null;
        return count($this->fdata[$name]["친구"]);
    }

    public function isRegistered(string $name) {
        return isset($this->fdata[mb_strtolower($name)]);
    }

    public function getGroupFriends(string $name, string $group) {
        $name = mb_strtolower($name);
        if (!$this->isRegistered($name))
            return null;
        return $this->fdata[$name]["그룹"][$group];
    }

    public function getGroups(string $name) {
        $name = mb_strtolower($name);
        if (!$this->isRegistered($name))
            return null;
        return $this->fdata[$name]["그룹"];
    }

    public function getGroupFriendCount(string $name, string $group) {
        $name = mb_strtolower($name);
        if (!$this->isRegistered($name))
            return null;
        return count($this->fdata[$name]["그룹"][$group]);
    }

    public function getRequestFriend(string $name) {
        $name = mb_strtolower($name);
        if (!$this->isRegistered($name))
            return false;
        return $this->fdata[$name]["수신여부"];
    }

    public function setRequestFriend(string $name, bool $type = true) {
        $name = mb_strtolower($name);
        if (!$this->isRegistered($name))
            return false;
        $this->fdata[$name]["수신여부"] = $type;
        return true;
    }

    private function RequestFriendUI(Player $player) {
        $form = $this->ui->SimpleForm(function (Player $player, array $data) {
            if (!isset($data[0]) || !isset($this->list[$player->getName()]) || !isset($this->list[$player->getName()][$data[0]])) return false;
            $friend = $this->list[$player->getName()][$data[0]];
            unset($this->list[$player->getName()]);
            $this->check($player, "친구 수락", $friend);
            return true;
        });
        $form->setTitle("Tele Friend");
        $text = "§l§c▶ §r§f친구 수신목록을 확인합니다.\n";
        $text .= "  선택시, 해당 유저와 친구관계를 맺습니다.";
        if ($this->getRequestFriendCount($player->getName()) <= 0) {
            $text .= "\n  친구가 없습니다..";
        } else {
            $count = 0;
            foreach ($this->getRequestFriends($player->getName()) as $key => $friend) {
                $this->list[$player->getName()][$count] = $friend;
                $form->addButton("§l{$friend}");
                $count++;
            }
        }
        $form->setContent($text);
        $form->sendToPlayer($player);
    }

    private function check(Player $player, string $type, string $object) {
        $this->list[$player->getName()]["type"] = $type;
        $this->list[$player->getName()]["object"] = $object;
        $form = $this->ui->ModalForm(function (Player $player, array $data) {
            $type = $this->list[$player->getName()]["type"];
            $object = $this->list[$player->getName()]["object"];
            unset($this->list[$player->getName()]);
            if ($data[0] == true) {
                if ($type == "친구 제거") {
                    if ($this->delFriend($player->getName(), $object)) {
                        $player->sendMessage("{$this->pre} {$object}님과의 친구관계를 끊었습니다.");
                        if (($friend = $this->getServer()->getPlayer($object)) instanceof Player)
                            $friend->sendMessage("{$this->pre} {$player->getName()}님이 당신과의 친구관계를 끊었습니다.");
                        return true;
                    } else {
                        $player->sendMessage("{$this->pre} 친구 제거에 실패하였습니다.");
                        return false;
                    }
                } elseif ($type == "친구 수락") {
                    if ($this->addFriend($player->getName(), $object)) {
                        $player->sendMessage("{$this->pre} {$object}님과의 친구관계를 맺었습니다.");
                        if (($friend = $this->getServer()->getPlayer($object)) instanceof Player)
                            $friend->sendMessage("{$this->pre} {$player->getName()}님이 당신과의 친구관계를 맺었습니다.");
                        return true;
                    } else {
                        $player->sendMessage("{$this->pre} 친구 수락에 실패하였습니다.");
                        return false;
                    }
                } elseif ($type == "그룹 제거") {
                    if ($this->delGroup($player->getName(), $object)) {
                        $player->sendMessage("{$this->pre} {$object} 그룹을 제거하였습니다.");
                        return true;
                    } else {
                        $player->sendMessage("{$this->pre} 그룹 제거에 실패하였습니다.");
                        return false;
                    }
                }
            } else {
                return false;
            }
        });
        $form->setTitle("Tele Friend");
        if ($type == "친구 제거") {
            $form->setContent("\n§l§c▶ §r§f정말 {$object}님과의 친구관계를\n  끊겠습니까?");
        } elseif ($type == "친구 수락") {
            $form->setContent("\n§l§c▶ §r§f정말 {$object}님과의 친구관계를\n  맺겠습니까?");
        } elseif ($type == "그룹 제거") {
            $form->setContent("\n§l§c▶ §r§f정말 {$object} 그룹을 제거하시겠습니까?");
        }
        $form->setButton1("§l§8[예]");
        $form->setButton2("§l§8[아니오]");
        $form->sendToPlayer($player);
    }

    public function delFriend(string $name, string $friend) {
        $name = mb_strtolower($name);
        $friend = mb_strtolower($friend);
        if (!$this->isRegistered($name) || !$this->isRegistered($friend) || !$this->isFriend($name, $friend) || !$this->isFriend($friend, $name))
            return false;
        $group_n = $this->getGroup($name, $friend);
        $group_f = $this->getGroup($friend, $name);
        unset($this->fdata[$name]["친구"][array_search($friend, $this->fdata[$name]["친구"])]);
        unset($this->fdata[$name]["그룹"][$group_n][array_search($friend, $this->fdata[$name]["그룹"][$group_n])]);
        unset($this->fdata[$name]["새메세지"][$friend]);
        unset($this->fdata[$friend]["친구"][array_search($name, $this->fdata[$friend]["친구"])]);
        unset($this->fdata[$friend]["그룹"][$group_f][array_search($name, $this->fdata[$friend]["그룹"][$group_f])]);
        unset($this->fdata[$friend]["새메세지"][$name]);
        return true;
    }

    public function isFriend(string $name, string $friend) {
        $name = mb_strtolower($name);
        $friend = mb_strtolower($friend);
        if (!$this->isRegistered($name) || !$this->isRegistered($friend))
            return false;
        return in_array($friend, $this->fdata[$name]["친구"]);
    }

    public function getGroup(string $name, string $friend) {
        $name = mb_strtolower($name);
        $friend = mb_strtolower($friend);
        if (!$this->isRegistered($name) || !$this->isRegistered($friend) || !$this->isFriend($name, $friend) || !$this->isFriend($friend, $name))
            return null;
        foreach ($this->fdata[$name]["그룹"] as $group => $friends) {
            if (in_array($friend, $this->fdata[$name]["그룹"][$group]))
                return $group;
        }
        return null;
    }

    public function addFriend(string $name, string $friend) {
        $name = mb_strtolower($name);
        $friend = mb_strtolower($friend);
        if (!$this->isRegistered($name) || !$this->isRegistered($friend) || $name == $friend)
            return false;
        $this->fdata[$name]["친구"][] = $friend;
        $this->fdata[$name]["그룹"]["일반"][] = $friend;
        $this->fdata[$name]["새메세지"][$friend] = 0;
        $this->fdata[$friend]["친구"][] = $name;
        $this->fdata[$friend]["그룹"]["일반"][] = $name;
        $this->fdata[$friend]["새메세지"][$name] = 0;
        if ($this->isRequestedFriend($name, $friend))
            unset($this->fdata[$friend]["수신목록"][array_search($name, $this->fdata[$friend]["수신목록"])]);
        if ($this->isRequestedFriend($friend, $name))
            unset($this->fdata[$name]["수신목록"][array_search($friend, $this->fdata[$name]["수신목록"])]);
        return true;
    }

    public function delGroup(string $name, string $group) {
        $name = mb_strtolower($name);
        if (!$this->isRegistered($name) || !$this->isExistGroup($name, $group) || $group == "일반")
            return false;
        foreach ($this->fdata[$name]["그룹"][$group] as $key => $friend) {
            $this->setGroup($name, $friend, "일반");
        }
        unset($this->fdata[$name]["그룹"][$group]);
        return true;
    }

    public function setGroup(string $name, string $friend, string $group) {
        $name = mb_strtolower($name);
        $friend = mb_strtolower($friend);
        $old_Group = $this->getGroup($name, $friend);
        if (!$this->isRegistered($name) || !$this->isRegistered($friend) || !$this->isFriend($name, $friend) || !$this->isFriend($friend, $name) || !$this->isExistGroup($name, $group) || $old_Group == $group)
            return false;
        unset($this->fdata[$name]["그룹"][$old_Group][array_search($friend, $this->fdata[$name]["그룹"][$old_Group])]);
        $this->fdata[$name]["그룹"][$group][] = $friend;
        return true;
    }

    public function getRequestFriendCount(string $name) {
        $name = mb_strtolower($name);
        if (!$this->isRegistered($name))
            return null;
        return count($this->fdata[$name]["수신목록"]);
    }

    public function getRequestFriends(string $name) {
        $name = mb_strtolower($name);
        if (!$this->isRegistered($name))
            return null;
        return $this->fdata[$name]["수신목록"];
    }

    private function ChatUI(Player $player) {
        $form = $this->ui->SimpleForm(function (Player $player, array $data) {
            if (!isset($data[0]) || !isset($this->list[$player->getName()]) || !isset($this->list[$player->getName()][$data[0]])) return false;
            $friend = $this->list[$player->getName()][$data[0]];
            unset($this->list[$player->getName()]);
            $this->ChatUI_1($player, $friend);
        });
        $form->setTitle("Tele Friend");
        $text = "§l§c▶ §r§f친구와 채팅을 합니다.\n";
        $text .= "  선택시, 해당 친구의 채팅창을 엽니다.";
        if ($this->getFriendCount($player->getName()) <= 0) {
            $text .= "\n  친구가 없습니다..";
        } else {
            $count = 0;
            foreach ($this->getFriends($player->getName()) as $key => $friend) {
                $this->list[$player->getName()][$count] = $friend;
                $form->addButton("§l{$friend}\n새로운 메세지: §c§l{$this->fdata[mb_strtolower($player->getName())]["새메세지"][mb_strtolower($friend)]}");
                $count++;
            }
        }
        $form->setContent($text);
        $form->sendToPlayer($player);
    }

    private function ChatUI_1(Player $player, string $target) {
        $this->list[$player->getName()] = $target;
        $form = $this->ui->CustomForm(function (Player $player, array $data) {
            $target = $this->list[$player->getName()];
            unset($this->list[$player->getName()]);
            if (!isset($data[1])) return false;
            if ($data[1] !== "") {
                $this->pushChatInfo($player->getName(), $target, $data[1]);
                if (($friend = $this->getServer()->getPlayer($target)) instanceof Player)
                    $friend->sendMessage("{$this->pre} {$player->getName()}님께 메세지가 왔습니다!");
            }
            $this->ChatUI_1($player, $target);
        });
        $form->setTitle("Tele Friend");
        $form->addLabel($this->getChatInfo($player->getName(), $target, $this->getToday(), 10));
        $form->addInput("§l§a▶ §r§f메세지\n  입력하지 않고 보낼시, 새로고침 됩니다.", "Message", "");
        $send = $form->sendToPlayer($player);
        $this->fdata[mb_strtolower($player->getName())]["새메세지"][mb_strtolower($target)] = 0;
        return $send;
    }

    public function pushChatInfo(string $name, string $friend, string $chat) {
        $name = mb_strtolower($name);
        $friend = mb_strtolower($friend);
        if (!$this->isRegistered($name) || !$this->isRegistered($friend))
            return false;

        if (file_exists($this->getChatPath() . $name . "/" . $friend . ".chat")) {
            $chat_file = fopen($this->getChatPath() . $name . "/" . $friend . ".chat", "a");
            $day = explode("날짜 변경\n", file_get_contents($this->getChatPath() . $name . "/" . $friend . ".chat"));
            $day = $day[count($day) - 1];
            $day = explode("\n\n", $day);
            if ($day[count($day) - 1] !== "")
                $day = $day[count($day) - 1];
            else
                $day = $day[count($day) - 2];
            $day = explode("|", $day);
            $day = $day[0];
            if ($this->getToday() !== $day)
                $value = "날짜 변경\n{$this->getToday()}|• {$name} > {$chat}§r§f\n  ({$this->getTime()})\n\n";
            else
                $value = "{$this->getToday()}|• {$name} > {$chat}§r§f\n  ({$this->getTime()})\n\n";
            fwrite($chat_file, $value, strlen($value));
            fclose($chat_file);
        } elseif (!file_exists($this->getChatPath() . $name . "/" . $friend . ".chat")) {
            file_put_contents($this->getChatPath() . $name . "/" . $friend . ".chat", "{$this->getToday()}|• {$name} > {$chat}§r§f\n  ({$this->getTime()})\n\n");
        }

        if (file_exists($this->getChatPath() . $friend . "/" . $name . ".chat")) {
            $chat_file = fopen($this->getChatPath() . $friend . "/" . $name . ".chat", "a");
            $day = explode("날짜 변경\n", file_get_contents($this->getChatPath() . $friend . "/" . $name . ".chat"));
            $day = $day[count($day) - 1];
            $day = explode("\n\n", $day);
            if ($day[count($day) - 1] !== "")
                $day = $day[count($day) - 1];
            else
                $day = $day[count($day) - 2];
            $day = explode("|", $day);
            $day = $day[0];
            if ($this->getToday() !== $day)
                $value = "날짜 변경\n{$this->getToday()}|• {$name} > {$chat}§r§f\n  ({$this->getTime()})\n\n";
            else
                $value = "{$this->getToday()}|• {$name} > {$chat}§r§f\n  ({$this->getTime()})\n\n";
            fwrite($chat_file, $value, strlen($value));
            fclose($chat_file);
        } elseif (!file_exists($this->getChatPath() . $friend . "/" . $name . ".chat")) {
            file_put_contents($this->getChatPath() . $friend . "/" . $name . ".chat", "{$this->getToday()}|• {$name} > {$chat}§r§f\n  ({$this->getTime()})\n\n");
        }
        if (!isset($this->fdata[$friend]["새메세지"][$name]))
            $this->fdata[$friend]["새메세지"][$name] = 0;
        $this->fdata[$friend]["새메세지"][$name]++;
        return false;
    }

    public function getToday() {
        return date("Y년 m월 d일", time());
    }

    public function getTime() {
        return date("h시 i분", time());
    }

////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
/////////////////////////////////////////////////////////////////////////////채팅창//////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    public function getChatInfo(string $name, string $friend, string $day, $cut = null) { // $day == $this->getToday();
        $name = mb_strtolower($name);
        $friend = mb_strtolower($friend);
        if (!$this->isRegistered($name) || !$this->isRegistered($friend) || !file_exists($this->getChatPath() . $name . "/" . $friend . ".chat"))
            return "";
        $chat_file = file_get_contents($this->getChatPath() . $name . "/" . $friend . ".chat");
        $chat_file = explode("날짜 변경\n", $chat_file);
        foreach ($chat_file as $key => $value) {
            $day_ = explode("\n\n", $value);
            if ($day_[count($day_) - 1] !== "")
                $day_ = $day_[count($day_) - 1];
            else
                $day_ = $day_[count($day_) - 2];
            $day_ = explode("|", $day_);
            $day_ = $day_[0];
            if ($day == $day_) {
                $chat = $value;
                if ($cut !== null && is_numeric($cut)) {
                    $chat = explode("\n\n", $chat);
                    if (count($chat) > $cut) {
                        $chat_ = $chat;
                        $chat = "";
                        for ($i = count($chat_) - $cut; $i < count($chat_); $i++) {
                            if ($i !== count($chat_) - 1)
                                $chat .= $chat_[$i] . "\n\n";
                            else
                                $chat .= $chat_[$i];
                        }
                    } else {
                        $chat = implode("\n\n", $chat);
                    }
                }
                $chat = str_replace("{$day}|", "", $chat);
                $chat = str_replace("• {$name} >", "• §l본인§r§f >", $chat);
                return $chat;
            }
        }
        return "";
    }

    public function getFriends(string $name) {
        $name = mb_strtolower($name);
        if (!$this->isRegistered($name))
            return null;
        return $this->fdata[$name]["친구"];
    }

////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
/////////////////////////////////////////////////////////////////////////////잡기능//////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    private function AddFriendUI(Player $player) {
        $form = $this->ui->CustomForm(function (Player $player, array $data) {
            if (!isset($data[1])) return false;
            if ($this->getServer()->getPlayer($data[1]) instanceof Player)
                $friend = $this->getServer()->getPlayer($data[1])->getName();
            elseif ($this->isRegistered($data[1]))
                $friend = mb_strtolower($data[1]);
            else
                $friend = null;
            if ($friend == null) {
                $player->sendMessage("{$this->pre} 해당 유저는 접속한 기록이 없습니다.");
                return false;
            }
            if ($this->isFriend($player->getName(), $friend)) {
                $player->sendMessage("{$this->pre} 해당 유저와는 이미 친구입니다.");
                return false;
            }
            if ($this->getRequestFriend($friend) == false) {
                $player->sendMessage("{$this->pre} 해당 유저는 친구요청을 거부한 상태입니다.");
                return false;
            }
            if ($this->isRequestedFriend($player->getName(), $friend)) {
                $player->sendMessage("{$this->pre} 해당 유저에게 이미 요청을 보냈습니다.");
                return false;
            }
            if ($this->isRequestedFriend($friend, $player->getName())) {
                $player->sendMessage("{$this->pre} 해당 유저에게 이미 요청이 온 상태입니다.");
                return false;
            }
            if ($this->requestFriend($player->getName(), $friend)) {
                $player->sendMessage("{$this->pre} {$friend}님께 친구요청을 보냈습니다.");
                if (($friend = $this->getServer()->getPlayer($friend)) instanceof Player)
                    $friend->sendMessage("{$this->pre} {$player->getName()}님으로부터 친구요청이 왔습니다!");
                return true;
            } else {
                $player->sendMessage("{$this->pre} 친구요청에 실패하였습니다.");
                return false;
            }
        });
        $form->setTitle("Tele Friend");
        $form->addLabel("§l§c▶ §r§f친구를 추가합니다. 아래칸에 닉네임을 기입해주세요.");
        $form->addInput("§l§a▶ §r§f친구 추가", "NickName");
        $form->sendToPlayer($player);
    }

    public function requestFriend(string $name, string $friend) {
        $name = mb_strtolower($name);
        $friend = mb_strtolower($friend);
        if (!$this->isRegistered($name) || !$this->isRegistered($friend) || $this->isRequestedFriend($name, $friend) || $this->isRequestedFriend($friend, $name))
            return false;
        $this->fdata[$friend]["수신목록"][] = $name;
        return true;
    }

    public function isRequestedFriend(string $name, string $friend) {
        $name = mb_strtolower($name);
        $friend = mb_strtolower($friend);
        if (!$this->isRegistered($name) || !$this->isRegistered($friend))
            return false;
        return in_array($name, $this->fdata[$friend]["수신목록"]);
    }

    private function DelFriendUI(Player $player) {
        $form = $this->ui->SimpleForm(function (Player $player, array $data) {
            if (!isset($data[0]) || !isset($this->list[$player->getName()]) || !isset($this->list[$player->getName()][$data[0]])) return false;
            $friend = $this->list[$player->getName()][$data[0]];
            unset($this->list[$player->getName()]);
            $this->check($player, "친구 제거", $friend);
            return true;
        });
        $form->setTitle("Tele Friend");
        $text = "§l§c▶ §r§f친구 관계를 끊습니다.\n";
        $text .= "  선택시, 해당 유저와의 친구관계를 끊습니다.";
        if ($this->getFriendCount($player->getName()) <= 0) {
            $text .= "\n  친구가 없습니다..";
        } else {
            $count = 0;
            foreach ($this->getFriends($player->getName()) as $key => $friend) {
                $this->list[$player->getName()][$count] = $friend;
                $form->addButton("§l{$friend}\n분류된 그룹: {$this->getGroup($player->getName(), $friend)}");
                $count++;
            }
        }
        $form->setContent($text);
        $form->sendToPlayer($player);
    }

    private function AddGroupUI(Player $player) {
        $form = $this->ui->CustomForm(function (Player $player, array $data) {
            if (!isset($data[1])) return false;
            if ($this->isExistGroup($player->getName(), $data[1])) {
                $player->sendMessage("{$this->pre} 해당 그룹은 이미 존재합니다.");
                return false;
            }
            if ($this->addGroup($player->getName(), $data[1])) {
                $player->sendMessage("{$this->pre} {$data[1]} 그룹을 신설하였습니다.");
                return true;
            } else {
                $player->sendMessage("{$this->pre} 그룹 추가에 실패하였습니다.");
                return false;
            }
        });
        $form->setTitle("Tele Friend");
        $form->addLabel("§l§c▶ §r§f세세히 분류할 그룹을 추가합니다. 아래칸에 그룹 이름을 기입해주세요.");
        $form->addInput("§l§a▶ §r§f그룹 추가", "GroupName");
        $form->sendToPlayer($player);
    }

    public function addGroup(string $name, string $group) {
        $name = mb_strtolower($name);
        if (!$this->isRegistered($name) || $this->isExistGroup($name, $group))
            return false;
        $this->fdata[$name]["그룹"][$group] = [];
        return true;
    }

    public function isExistGroup(string $name, string $group) {
        return isset($this->fdata[mb_strtolower($name)]["그룹"][$group]);
    }

    private function DelGroupUI(Player $player) {
        $form = $this->ui->SimpleForm(function (Player $player, array $data) {
            if (!isset($data[0]) || !isset($this->list[$player->getName()]) || !isset($this->list[$player->getName()][$data[0]])) return false;
            $group = $this->list[$player->getName()][$data[0]];
            unset($this->list[$player->getName()]);
            if ($group == "일반") {
                $player->sendMessage("{$this->pre} 일반 그룹은 제거할 수 없습니다.");
                return false;
            }
            $this->check($player, "그룹 제거", $group);
            return true;
        });
        $form->setTitle("Tele Friend");
        $text = "§l§c▶ §r§f세세히 분류한 그룹을 제거합니다.\n";
        $text .= "  선택시, 해당 그룹을 제거되며 해당 그룹의 친구는 일반으로 옮겨집니다.";
        $count = 0;
        foreach ($this->getGroups($player->getName()) as $group => $friends) {
            $this->list[$player->getName()][$count] = $group;
            $form->addButton("§l{$group}");
            $count++;
        }
        $form->setContent($text);
        $form->sendToPlayer($player);
    }

    private function SetGroupUI(Player $player) {
        $form = $this->ui->SimpleForm(function (Player $player, array $data) {
            if (!isset($data[0]) || !isset($this->list[$player->getName()]) || !isset($this->list[$player->getName()][$data[0]])) return false;
            $friend = $this->list[$player->getName()][$data[0]];
            unset($this->list[$player->getName()]);
            $this->SetGroupUI_1($player, $friend);
            return true;
        });
        $form->setTitle("Tele Friend");
        $text = "§l§c▶ §r§f친구를 그룹별로 분류합니다.";
        $count = 0;
        foreach ($this->getFriends($player->getName()) as $key => $friend) {
            $this->list[$player->getName()][$count] = $friend;
            $form->addButton("§l{$friend}\n§r§8분류된 그룹: {$this->getGroup($player->getName(), $friend)}");
            $count++;
        }
        $form->setContent($text);
        $form->sendToPlayer($player);
    }

    private function SetGroupUI_1(Player $player, string $friend) {
        $this->list[$player->getName()]["친구"] = $friend;
        $form = $this->ui->SimpleForm(function (Player $player, array $data) {
            if (!isset($data[0]) || !isset($this->list[$player->getName()]) || !isset($this->list[$player->getName()]["그룹"][$data[0]])) return false;
            $group = $this->list[$player->getName()]["그룹"][$data[0]];
            $friend = $this->list[$player->getName()]["친구"];
            unset($this->list[$player->getName()]);
            $this->setGroup($player->getName(), $friend, $group);
            $this->SetGroupUI($player);
            return true;
        });
        $form->setTitle("Tele Friend");
        $text = "§l§c▶ §r§f분류할 그룹을 선택해주세요.";
        $count = 0;
        foreach ($this->getGroups($player->getName()) as $group => $friends) {
            $this->list[$player->getName()]["그룹"][$count] = $group;
            $form->addButton("§l{$group}");
            $count++;
        }
        $form->setContent($text);
        $form->sendToPlayer($player);
    }
}
