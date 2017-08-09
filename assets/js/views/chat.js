Chat.Views.ChatView = Backbone.View.extend({
    el: '.chat-wrapper',
    events: {
        'click #send-msg': 'sendMessage',
        'keypress #chat-message': 'checkKey'
    },
    input: '#chat-message',
    initialize: function () {
        console.log('chat view init');
        var self = this;
        Chat.vent.on('message:add', self.renderMessage, self);
        //event will be triggered when user was authorized in chat
        Chat.vent.on('user:setCurrent', function (user) {
            console.log('user model init');
            self.model = user;
        }, self);
        //event will be triggered when user was clicked in list
        Chat.vent.on('user:select', self.selectUser, self);
        Chat.vent.on('history:load', self.loadHistory, self);
        console.log('chat view init end');
    },
    renderMessage: function (message) {
        var msg = new Chat.Views.Message({model: message});
        var $container = this.$el.find('.chat-container');
        $container.append(msg.render().el);
        $container.animate({
            scrollTop: $container[0].scrollHeight
        }, 'slow');
        return this;
    },
    sendMessage: function () {
        console.log('sendMessage init');
        var $messageInput = this.$el.find('#chat-message');
        var $toInput = this.$el.find('#chat-message-to');
        var msg = $messageInput.val();
        var to = $toInput.val();
        $messageInput.val('');
        console.log('msg to ' + to);
        if (msg) {
            //clear timestamp - it auto generated
            this.model.set('timestamp', '');
            this.model.set('message', msg);
            this.model.set('to', to || 0);
            this.model.set('type', 'info');
            this.renderMessage(this.model);
            Chat.vent.trigger('message:send', {message: msg, to: to});
        }
    },
    loadHistory: function (data) {
        for (var key in data) {
            var item = data[key];
            var user = {
                id: item.user_id, username: item.username, timestamp: item.timestamp,
                message: item.message, avatar_16: item.avatar_16, avatar_32: item.avatar_32,
                type: 'info'
            };
            this.renderMessage(new Chat.Models.User(user));
        }
    },
    checkKey: function (e) {
        //check if enter is pressed
        if (e.keyCode === 13) {
            this.sendMessage();
        }
    },
    selectUser: function (el) {
        var user = el.find('div');
        var name = user.text().trim();

        this.$el.find('#chat-message').val('@' + name + ':');
        this.$el.find('#chat-message-to').val(user.data('to'));
    }
});