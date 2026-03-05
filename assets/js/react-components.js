
// 定义cn函数，用于合并CSS类名
function cn(...args) {
    return args.filter(Boolean).join(' ');
}

// 定义InteractiveHoverButton组件
const InteractiveHoverButton = React.forwardRef(function(props, ref) {
    const [currentText, setCurrentText] = React.useState(props.text || "Button");
    
    React.useEffect(() => {
        const handleLanguageChange = (e) => {
            const lang = e.detail;
            if (window.content && window.content[lang] && window.content[lang].goToBookStore) {
                setCurrentText(window.content[lang].goToBookStore);
            }
        };
        
        // 如果已经有全局语言状态，初始化文字
        if (window.currentLanguage && window.content && window.content[window.currentLanguage]) {
            setCurrentText(window.content[window.currentLanguage].goToBookStore);
        }

        window.addEventListener('languageChanged', handleLanguageChange);
        return () => window.removeEventListener('languageChanged', handleLanguageChange);
    }, []);

    const { text, className, ...restProps } = props;
    
    // 添加点击波纹效果处理函数和页面跳转
    const handleClick = (e) => {
        const button = e.currentTarget;
        const ripple = document.createElement('span');
        const rect = button.getBoundingClientRect();
        const size = Math.max(rect.width, rect.height);
        const x = e.clientX - rect.left - size/2;
        const y = e.clientY - rect.top - size/2;
        
        ripple.style.width = ripple.style.height = size + 'px';
        ripple.style.left = x + 'px';
        ripple.style.top = y + 'px';
        ripple.classList.add('ripple');
        
        const rippleElements = button.getElementsByClassName('ripple');
        if (rippleElements.length > 0) {
            button.removeChild(rippleElements[0]);
        }
        
        button.appendChild(ripple);
        
        // 调用原始的点击事件处理函数
        if (restProps.onClick) {
            restProps.onClick(e);
        }
        
        // 跳转到冠恩书屋页面
        window.location.href = "pages/article.html";
    };
    
    return React.createElement(
        'button',
        {
            ref: ref,
            className: cn(
                "group relative cursor-pointer overflow-hidden rounded-full border bg-background p-2 text-center font-semibold flex items-center justify-center gap-2",
                className
            ),
            onClick: handleClick,
            ...restProps
        },
        // 只保留一个文本元素，添加更丰富的动画效果
        React.createElement(
            'span',
            {
                className: "inline-block transition-all duration-300 group-hover:scale-105"
            },
            currentText
        ),
        // 添加图标 - 与文字在同一行显示
        React.createElement('svg', {
            xmlns: "http://www.w3.org/2000/svg",
            width: "16",
            height: "16",
            viewBox: "0 0 24 24",
            fill: "none",
            stroke: "currentColor",
            strokeWidth: "2",
            strokeLinecap: "round",
            strokeLinejoin: "round",
            className: "inline-block transition-all duration-300 group-hover:translate-x-1"
        }, React.createElement('line', { x1: "5", y1: "12", x2: "19", y2: "12" }), React.createElement('polyline', { points: "12 5 19 12 12 19" })),
        // 背景动画效果
        React.createElement(
            'div',
            {
                className: "absolute left-[20%] top-[40%] h-2 w-2 scale-[1] rounded-full bg-primary/20 transition-all duration-500 group-hover:left-0 group-hover:top-0 group-hover:h-full group-hover:w-full group-hover:scale-[1.5] group-hover:bg-primary/10"
            }
        )
    );
});

InteractiveHoverButton.displayName = "InteractiveHoverButton";

// 渲染InteractiveHoverButton组件 - 更优雅的版本
ReactDOM.render(
    React.createElement(InteractiveHoverButton, { 
        text: "前往冠恩书屋", 
        className: "interactive-hover-button elegantly-styled-button"
    }),
    document.getElementById('interactiveHoverButton')
);

// 定义并渲染GreetingSection组件（如果存在）
if (typeof React !== 'undefined' && typeof ReactDOM !== 'undefined') {
    // 定义GreetingSection组件
    const GreetingSection = React.forwardRef(function(props, ref) {
        const { title, subtitle, buttons } = props;
        
        return React.createElement(
            'section',
            { 
                ref: ref,
                className: "relative flex flex-col items-center justify-center gap-8 px-6 py-16 sm:py-24"
            },
            React.createElement(
                'h2',
                { className: "text-4xl font-bold tracking-tight text-center sm:text-6xl" },
                title
            ),
            React.createElement(
                'p',
                { className: "max-w-2xl text-center text-lg leading-relaxed text-gray-700" },
                subtitle
            ),
            React.createElement(
                'div',
                { className: "flex flex-wrap justify-center gap-4" },
                buttons.map((button, index) => React.createElement(
                    InteractiveHoverButton,
                    { 
                        key: index,
                        ...button
                    }
                ))
            )
        );
    });

    // 检查DOM是否已加载
    document.addEventListener('DOMContentLoaded', function() {
        // 渲染GreetingSection组件到DOM
        const greetingSectionElement = document.getElementById('greeting-section');
        if (greetingSectionElement) {
            const root = ReactDOM.createRoot(greetingSectionElement);
            root.render(React.createElement(
                GreetingSection,
                {
                    title: "欢迎来到冠恩超人网站",
                    subtitle: "探索无限可能，创造美好未来。在这里，你可以找到灵感、知识和志同道合的朋友。",
                    buttons: [
                        { text: "了解更多", className: "border-blue-600 text-blue-600 hover:text-white" },
                        { text: "前往冠恩书屋", className: "border-gray-800 text-gray-800 hover:text-white" }
                    ]
                }
            ));
        }
    });
}

